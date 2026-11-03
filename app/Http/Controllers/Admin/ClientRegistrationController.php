<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ClientRegistrationStatus;
use App\Enums\ClientService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Clients\ApproveClientRegistrationRequest;
use App\Http\Requests\Clients\RejectClientRegistrationRequest;
use App\Models\ClientRegistration;
use App\Models\User;
use App\Services\ClientOnboarding;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The review queue for prospective client applications.
 */
class ClientRegistrationController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(protected ClientOnboarding $onboarding) {}

    /**
     * List the applications submitted through the public form.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ClientRegistration::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'status' => $request->string('status')->toString(),
        ];

        /** @var LengthAwarePaginator<int, array<string, mixed>> $registrations */
        $registrations = ClientRegistration::query()
            ->with(['client:id,name', 'reviewer:id,name'])
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $term = '%'.$filters['search'].'%';

                $query->where(function ($query) use ($term): void {
                    $query->where('company_name', 'like', $term)
                        ->orWhere('contact_name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('phone', 'like', $term);
                });
            })
            ->when($filters['status'] !== '', fn ($query) => $query->where('status', $filters['status']))
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (ClientRegistration $registration): array => $this->row($registration));

        return Inertia::render('admin/clients/registrations', [
            'registrations' => $registrations,
            'filters' => $filters,
            'stats' => [
                'pending' => ClientRegistration::query()->awaitingReview()->count(),
                'approved' => ClientRegistration::query()
                    ->where('status', ClientRegistrationStatus::Approved->value)
                    ->count(),
                'rejected' => ClientRegistration::query()
                    ->where('status', ClientRegistrationStatus::Rejected->value)
                    ->count(),
                'total' => ClientRegistration::query()->count(),
            ],
            'statuses' => array_map(
                fn (ClientRegistrationStatus $status): array => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ],
                ClientRegistrationStatus::cases(),
            ),
            'services' => array_map(
                fn (ClientService $service): array => [
                    'value' => $service->value,
                    'label' => $service->label(),
                ],
                ClientService::cases(),
            ),
        ]);
    }

    /**
     * Approve an application, creating the tenant and inviting the applicant.
     */
    public function approve(ApproveClientRegistrationRequest $request, ClientRegistration $registration): RedirectResponse
    {
        $reviewer = $request->user();

        if (! $reviewer instanceof User) {
            abort(403);
        }

        $client = $this->onboarding->approve($registration, $reviewer, $request->overrides());

        return to_route('admin.clients.show', $client)->with('toast', [
            'type' => 'success',
            'message' => "{$client->name} created as a pending client and the applicant invited.",
        ]);
    }

    /**
     * Reject an application with a reason the applicant can be shown.
     */
    public function reject(RejectClientRegistrationRequest $request, ClientRegistration $registration): RedirectResponse
    {
        $reviewer = $request->user();

        if (! $reviewer instanceof User) {
            abort(403);
        }

        $this->onboarding->reject($registration, $reviewer, (string) $request->validated('reason'));

        return back()->with('toast', [
            'type' => 'warning',
            'message' => "Application from {$registration->company_name} rejected.",
        ]);
    }

    /**
     * Present an application for the review queue.
     *
     * @return array<string, mixed>
     */
    protected function row(ClientRegistration $registration): array
    {
        return [
            'id' => $registration->id,
            'company_name' => $registration->company_name,
            'contact_name' => $registration->contact_name,
            'email' => $registration->email,
            'phone' => $registration->phone,
            'country' => $registration->country,
            'timezone' => $registration->timezone,
            'website' => $registration->website,
            'services' => array_map(
                fn (ClientService $service): string => $service->label(),
                $registration->requestedServices(),
            ),
            'expected_monthly_volume' => $registration->expected_monthly_volume,
            'message' => $registration->message,
            'status' => $registration->status->value,
            'status_label' => $registration->status->label(),
            'is_pending' => $registration->isPending(),
            'client_id' => $registration->client_id,
            'client' => $registration->client?->name,
            'reviewer' => $registration->reviewer?->name,
            'reviewed_at' => $registration->reviewed_at?->toIso8601String(),
            'rejection_reason' => $registration->rejection_reason,
            'created_at' => $registration->created_at?->toIso8601String(),
        ];
    }
}
