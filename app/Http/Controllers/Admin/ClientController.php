<?php

namespace App\Http\Controllers\Admin;

use App\Concerns\RecordsActivity;
use App\Enums\ClientDocumentStatus;
use App\Enums\ClientDocumentType;
use App\Enums\ClientService;
use App\Enums\ClientStatus;
use App\Exceptions\InvalidClientTransition;
use App\Http\Controllers\Controller;
use App\Http\Requests\Clients\TransitionClientRequest;
use App\Http\Requests\Clients\UpdateClientRequest;
use App\Models\Client;
use App\Models\ClientDocument;
use App\Models\ClientEntitlement;
use App\Models\ClientRegistration;
use App\Models\User;
use App\Services\ClientLifecycle;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The client tenant directory: profile, lifecycle, documents and entitlements.
 */
class ClientController extends Controller
{
    use RecordsActivity;

    /**
     * Create a new controller instance.
     */
    public function __construct(protected ClientLifecycle $lifecycle) {}

    /**
     * List every tenant on the platform.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Client::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'status' => $request->string('status')->toString(),
            'view' => $request->string('view')->toString(),
        ];

        /** @var LengthAwarePaginator<int, array<string, mixed>> $clients */
        $clients = Client::query()
            ->withCount([
                'users',
                'documents',
                'documents as pending_documents_count' => fn ($query) => $query
                    ->where('status', ClientDocumentStatus::Pending->value),
            ])
            ->with(['entitlements:id,client_id,service,is_enabled'])
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $term = '%'.$filters['search'].'%';

                $query->where(function ($query) use ($term): void {
                    $query->where('name', 'like', $term)
                        ->orWhere('code', 'like', $term)
                        ->orWhere('email', 'like', $term);
                });
            })
            ->when($filters['status'] !== '', fn ($query) => $query->where('status', $filters['status']))
            ->when($filters['view'] === 'documents', fn ($query) => $query->whereHas('documents'))
            ->when($filters['view'] === 'users', fn ($query) => $query->whereHas('users'))
            ->when($filters['view'] === 'services', fn ($query) => $query->whereHas('entitlements'))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Client $client): array => $this->row($client));

        return Inertia::render('admin/clients/index', [
            'clients' => $clients,
            'filters' => $filters,
            'stats' => [
                'total' => Client::query()->count(),
                'pending' => Client::query()->pending()->count(),
                'active' => Client::query()->active()->count(),
                'suspended' => Client::query()->withStatus(ClientStatus::Suspended)->count(),
                'archived' => Client::query()->withStatus(ClientStatus::Archived)->count(),
                'applications' => ClientRegistration::query()->awaitingReview()->count(),
            ],
            'statuses' => array_map(
                fn (ClientStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
                ClientStatus::cases(),
            ),
            'can' => [
                'review' => Gate::allows('viewAny', ClientRegistration::class),
            ],
        ]);
    }

    /**
     * Show a tenant with its documents, entitlements and lifecycle history.
     */
    public function show(Client $client): Response
    {
        Gate::authorize('view', $client);

        $client->load([
            'documents' => fn ($query) => $query
                ->with(['uploader:id,name', 'reviewer:id,name'])
                ->latest(),
            'entitlements',
            'registrations' => fn ($query) => $query->with('reviewer:id,name')->latest(),
        ]);

        $client->loadCount(['users', 'campaigns', 'contacts']);

        $missing = $client->missingRequiredDocuments();

        return Inertia::render('admin/clients/show', [
            'client' => $this->detail($client),
            'documents' => $client->documents
                ->map(fn (ClientDocument $document): array => $this->documentRow($document))
                ->all(),
            'entitlements' => $this->entitlementRows($client),
            'documentTypes' => array_map(
                fn (ClientDocumentType $type): array => [
                    'value' => $type->value,
                    'label' => $type->label(),
                    'is_required' => in_array($type, $client->accountType()->identificationTypes(), true),
                ],
                $client->accountType()->identificationTypes(),
            ),
            'missingDocuments' => array_map(
                fn (ClientDocumentType $type): string => $type->label(),
                $missing,
            ),
            'can' => [
                'update' => Gate::allows('update', $client),
                'transition' => Gate::allows('transition', $client),
                'approve' => Gate::allows('approve', $client),
                'activate' => $client->hasVerifiedIdentification(),
                'transitions' => array_map(
                    fn (ClientStatus $status): string => $status->value,
                    $client->status->allowedTransitions(),
                ),
            ],
        ]);
    }

    /**
     * Update the tenant's profile.
     */
    public function update(UpdateClientRequest $request, Client $client): RedirectResponse
    {
        $before = $client->only(['name', 'email', 'phone', 'timezone', 'credit_limit', 'notes']);

        $client->update($request->clientAttributes());

        $this->recordActivity('updated client', $client, [
            'before' => $before,
            'after' => $client->only(['name', 'email', 'phone', 'timezone', 'credit_limit', 'notes']),
        ]);

        return back()->with('toast', [
            'type' => 'success',
            'message' => "{$client->name} updated.",
        ]);
    }

    /**
     * Sign off a pending tenant once its documents are verified.
     */
    public function activate(Request $request, Client $client): RedirectResponse
    {
        Gate::authorize('transition', $client);

        $missing = $client->missingRequiredDocuments();

        if (! $client->hasVerifiedIdentification()) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => 'Verify one identification document first. Accepted documents: '
                    .implode(', ', array_map(fn (ClientDocumentType $type): string => $type->label(), $missing))
                    .'.',
            ]);
        }

        return $this->move(
            $client,
            ClientStatus::Active,
            null,
            $request,
            'Client activated. Broadcasts are now allowed.',
        );
    }

    /**
     * Put a tenant on hold, blocking its broadcasts and sessions.
     */
    public function suspend(TransitionClientRequest $request, Client $client): RedirectResponse
    {
        return $this->move(
            $client,
            ClientStatus::Suspended,
            (string) $request->validated('reason'),
            $request,
            'Client suspended. Broadcasts are blocked and its users are signed out.',
        );
    }

    /**
     * Bring a suspended tenant back into service.
     */
    public function reactivate(Request $request, Client $client): RedirectResponse
    {
        Gate::authorize('transition', $client);

        return $this->move($client, ClientStatus::Active, null, $request, 'Client reactivated.');
    }

    /**
     * Close the tenant permanently.
     */
    public function archive(TransitionClientRequest $request, Client $client): RedirectResponse
    {
        return $this->move(
            $client,
            ClientStatus::Archived,
            (string) $request->validated('reason'),
            $request,
            'Client archived. The relationship is closed.',
        );
    }

    /**
     * Apply a lifecycle move and translate the outcome into a toast.
     */
    protected function move(
        Client $client,
        ClientStatus $target,
        ?string $reason,
        Request $request,
        string $message,
    ): RedirectResponse {
        try {
            $this->lifecycle->transition($client, $target, $reason, $this->actor($request));
        } catch (InvalidClientTransition $exception) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => $exception->getMessage(),
            ]);
        }

        return back()->with('toast', [
            'type' => 'success',
            'message' => $message,
        ]);
    }

    /**
     * The signed-in administrator, when the request was authenticated normally.
     */
    protected function actor(Request $request): ?User
    {
        $user = $request->user();

        return $user instanceof User ? $user : null;
    }

    /**
     * Present a tenant for the directory table.
     *
     * @return array<string, mixed>
     */
    protected function row(Client $client): array
    {
        return [
            'id' => $client->id,
            'name' => $client->name,
            'account_type' => $client->accountType()->value,
            'account_type_label' => $client->accountType()->label(),
            'code' => $client->code,
            'email' => $client->email,
            'phone' => $client->phone,
            'timezone' => $client->timezone,
            'status' => $client->status->value,
            'status_label' => $client->status->label(),
            'status_reason' => $client->status_reason,
            'credit_limit' => (float) $client->credit_limit,
            'users_count' => (int) ($client->users_count ?? 0),
            'documents_count' => (int) ($client->documents_count ?? 0),
            'pending_documents_count' => (int) ($client->pending_documents_count ?? 0),
            'services' => $client->entitlements
                ->filter(fn (ClientEntitlement $entitlement): bool => $entitlement->is_enabled)
                ->map(fn (ClientEntitlement $entitlement): string => $entitlement->service->label())
                ->values()
                ->all(),
            'status_changed_at' => $client->status_changed_at?->toIso8601String(),
            'activated_at' => $client->activated_at?->toIso8601String(),
            'suspended_at' => $client->suspended_at?->toIso8601String(),
            'archived_at' => $client->archived_at?->toIso8601String(),
            'created_at' => $client->created_at?->toIso8601String(),
        ];
    }

    /**
     * Present a tenant for the detail screen.
     *
     * @return array<string, mixed>
     */
    protected function detail(Client $client): array
    {
        return [
            ...$this->row($client),
            'notes' => $client->notes,
            'campaigns_count' => (int) ($client->campaigns_count ?? 0),
            'contacts_count' => (int) ($client->contacts_count ?? 0),
            'registrations' => $client->registrations
                ->map(fn (ClientRegistration $registration): array => [
                    'id' => $registration->id,
                    'company_name' => $registration->company_name,
                    'status' => $registration->status->value,
                    'status_label' => $registration->status->label(),
                    'reviewer' => $registration->reviewer?->name,
                    'reviewed_at' => $registration->reviewed_at?->toIso8601String(),
                    'created_at' => $registration->created_at?->toIso8601String(),
                ])
                ->all(),
        ];
    }

    /**
     * Present one uploaded document.
     *
     * @return array<string, mixed>
     */
    protected function documentRow(ClientDocument $document): array
    {
        return [
            'id' => $document->id,
            'type' => $document->type->value,
            'type_label' => $document->type->label(),
            'title' => $document->title,
            'status' => $document->status->value,
            'status_label' => $document->status->label(),
            'original_name' => $document->original_name,
            'mime_type' => $document->mime_type,
            'size' => $document->displaySize(),
            'rejection_reason' => $document->rejection_reason,
            'uploaded_by' => $document->uploader?->name,
            'reviewed_by' => $document->reviewer?->name,
            'reviewed_at' => $document->reviewed_at?->toIso8601String(),
            'expires_at' => $document->expires_at?->toIso8601String(),
            'created_at' => $document->created_at?->toIso8601String(),
        ];
    }

    /**
     * The full entitlement grid, including services without a configured row.
     *
     * @return list<array<string, mixed>>
     */
    protected function entitlementRows(Client $client): array
    {
        $rows = [];

        foreach (ClientService::cases() as $service) {
            $entitlement = $client->entitlements->first(
                fn (ClientEntitlement $candidate): bool => $candidate->service === $service,
            );

            $rows[] = [
                'service' => $service->value,
                'service_label' => $service->label(),
                'is_enabled' => $entitlement?->is_enabled ?? true,
                'monthly_quota' => $entitlement?->monthly_quota,
                'daily_quota' => $entitlement?->daily_quota,
                'notes' => $entitlement?->notes,
                'quota_label' => $entitlement?->quotaLabel() ?? 'Unlimited',
            ];
        }

        return $rows;
    }
}
