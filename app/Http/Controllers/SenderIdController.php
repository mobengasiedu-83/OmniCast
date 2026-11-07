<?php

namespace App\Http\Controllers;

use App\Enums\ClientService;
use App\Enums\SenderIdChannel;
use App\Enums\SenderIdStatus;
use App\Http\Controllers\Concerns\ResolvesTenantClient;
use App\Http\Requests\SenderId\SenderIdRequest;
use App\Models\Client;
use App\Models\SenderId;
use App\Services\ModuleAvailability;
use App\Services\SenderIdWorkflow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SenderIdController extends Controller
{
    use ResolvesTenantClient;

    public function __construct(
        protected SenderIdWorkflow $workflow,
        protected ModuleAvailability $modules,
    ) {}

    /**
     * List the sender IDs requested by the client tenant.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', SenderId::class);

        $client = $this->tenantClient($request);

        $filters = [
            'search' => $request->string('search')->toString(),
            'channel' => $request->string('channel')->toString(),
            'status' => $request->string('status')->toString(),
            'client_id' => $client?->id,
        ];

        $senderIds = $this->scopedQuery($request, $client)
            ->with(['client:id,name', 'requester:id,name'])
            ->withCount('campaigns')
            ->when($filters['status'] !== '', fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($filters['channel'] !== '', fn (Builder $query) => $query->where('channel', $filters['channel']))
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                /**
                 * The name column is case-sensitive so brands keep their case,
                 * which means the search has to fold both sides itself.
                 */
                $needle = '%'.Str::lower($filters['search']).'%';

                $query->where(function (Builder $query) use ($needle): void {
                    $query->whereRaw('LOWER(name) LIKE ?', [$needle])
                        ->orWhere('purpose', 'like', $needle);
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (SenderId $senderId): array => $this->row($senderId));

        return Inertia::render('sender-ids/index', [
            'senderIds' => $senderIds,
            'filters' => $filters,
            'stats' => [
                'total' => $this->scopedQuery($request, $client)->count(),
                'pending' => $this->scopedQuery($request, $client)
                    ->whereIn('status', [SenderIdStatus::Pending->value, SenderIdStatus::Submitted->value])
                    ->count(),
                'approved' => $this->scopedQuery($request, $client)->where('status', SenderIdStatus::Approved->value)->count(),
                'rejected' => $this->scopedQuery($request, $client)->where('status', SenderIdStatus::Rejected->value)->count(),
            ],
            'channels' => $this->channelOptions($client),
            'statuses' => $this->statusOptions(),
            'clients' => $this->selectableClients($request),
            'can' => [
                'create' => Gate::allows('create', SenderId::class),
                'approve' => $request->user()->can('sender-ids.approve'),
            ],
        ]);
    }

    /**
     * Show the request form for a new sender ID.
     */
    public function create(Request $request): Response
    {
        Gate::authorize('create', SenderId::class);

        return Inertia::render('sender-ids/create', $this->formOptions($request));
    }

    /**
     * Submit a new sender ID request and email the platform reviewers.
     */
    public function store(SenderIdRequest $request): RedirectResponse
    {
        $senderId = $this->workflow->request($request->senderIdAttributes(), $request->user());

        return to_route('sender-ids.index')->with('toast', [
            'type' => 'success',
            'message' => "{$senderId->displayName()} submitted. Compliance has been emailed.",
        ]);
    }

    /**
     * Show the form for changing a request that has not gone live yet.
     */
    public function edit(Request $request, SenderId $senderId): Response
    {
        Gate::authorize('update', $senderId);

        $senderId->loadMissing(['client:id,name', 'requester:id,name']);

        return Inertia::render('sender-ids/edit', [
            ...$this->formOptions($request),
            'senderId' => [
                ...$this->row($senderId),
                'client_id' => $senderId->client_id,
            ],
        ]);
    }

    /**
     * Apply the client's edits and send the request back to review.
     */
    public function update(SenderIdRequest $request, SenderId $senderId): RedirectResponse
    {
        Gate::authorize('update', $senderId);

        $this->workflow->resubmit($senderId, $request->senderIdAttributes(), $request->user());

        return to_route('sender-ids.index')->with('toast', [
            'type' => 'success',
            'message' => "{$senderId->displayName()} resubmitted for review. Compliance has been emailed.",
        ]);
    }

    /**
     * Approve a pending sender ID request.
     */
    public function approve(Request $request, SenderId $senderId): RedirectResponse
    {
        Gate::authorize('approve', $senderId);

        $this->workflow->approve($senderId, $request->user());

        return back()->with('toast', [
            'type' => 'success',
            'message' => "{$senderId->displayName()} approved.".$this->requesterNotified($senderId),
        ]);
    }

    /**
     * Reject a pending sender ID request.
     */
    public function reject(Request $request, SenderId $senderId): RedirectResponse
    {
        Gate::authorize('approve', $senderId);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $this->workflow->reject($senderId, $request->user(), $validated['reason']);

        return back()->with('toast', [
            'type' => 'warning',
            'message' => "{$senderId->displayName()} rejected.".$this->requesterNotified($senderId),
        ]);
    }

    /**
     * Withdraw a sender ID that has not been approved.
     */
    public function destroy(SenderId $senderId): RedirectResponse
    {
        Gate::authorize('delete', $senderId);

        $name = $senderId->displayName();

        $senderId->delete();

        return back()->with('toast', [
            'type' => 'success',
            'message' => "{$name} request withdrawn.",
        ]);
    }

    /**
     * The tenant-scoped sender ID query shared by the listing and its counters.
     *
     * A tenant never sees identities that belong to a module it has switched
     * off, while the platform team keeps its full cross-client view.
     *
     * @return Builder<SenderId>
     */
    protected function scopedQuery(Request $request, ?Client $client): Builder
    {
        return SenderId::query()
            ->forUser($request->user())
            ->when($client !== null, fn (Builder $query) => $query->where($query->qualifyColumn('client_id'), $client->id))
            ->when($client !== null, fn (Builder $query) => $query->whereIn(
                $query->qualifyColumn('channel'),
                array_column($this->channelOptions($client), 'value'),
            ));
    }

    /**
     * Shape a sender ID for the client-side listing and edit form.
     *
     * @return array<string, mixed>
     */
    protected function row(SenderId $senderId): array
    {
        return [
            'id' => $senderId->id,
            'name' => $senderId->name,
            'channel' => $senderId->channel->value,
            'channel_label' => $senderId->channel->label(),
            'identifier_label' => $senderId->channel->identifierLabel(),
            'country' => $senderId->country,
            'purpose' => $senderId->purpose,
            'status' => $senderId->status->value,
            'status_label' => $senderId->status->label(),
            'requested_at' => $senderId->requested_at?->toIso8601String(),
            'approved_at' => $senderId->approved_at?->toIso8601String(),
            'rejection_reason' => $senderId->rejection_reason,
            'requester' => $senderId->requester?->name,
            'client' => $senderId->client?->name,
            'campaigns_count' => (int) $senderId->getAttribute('campaigns_count'),
            'can' => [
                'update' => Gate::allows('update', $senderId),
                'delete' => Gate::allows('delete', $senderId),
                'approve' => Gate::allows('approve', $senderId),
            ],
        ];
    }

    /**
     * The options shared by the create and edit forms.
     *
     * @return array<string, mixed>
     */
    protected function formOptions(Request $request): array
    {
        $client = $this->tenantClient($request);

        return [
            'channels' => $this->channelOptions($client),
            'clients' => $this->selectableClients($request),
            'tenantClientId' => $client?->id,
        ];
    }

    /**
     * The sender identity channels this tenant may still request.
     *
     * @return list<array<string, mixed>>
     */
    protected function channelOptions(?Client $client = null): array
    {
        $options = [];

        foreach (SenderIdChannel::cases() as $channel) {
            $module = ClientService::tryFrom($channel->value);

            if ($module !== null && ! $this->modules->enabled($module, $client)) {
                continue;
            }

            $options[] = [
                'value' => $channel->value,
                'label' => $channel->label(),
                'description' => $channel->description(),
                'identifier_label' => $channel->identifierLabel(),
                'placeholder' => $channel->placeholder(),
                'hint' => $channel->hint(),
                'requires_country' => $channel->requiresCountry(),
                'max_length' => $channel->maxLength(),
            ];
        }

        return $options;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    protected function statusOptions(): array
    {
        return array_map(
            fn (SenderIdStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
            SenderIdStatus::cases(),
        );
    }

    /**
     * Tell the reviewer whether the decision email reached the requester.
     */
    protected function requesterNotified(SenderId $senderId): string
    {
        return $this->workflow->requesterEmail($senderId) === ''
            ? ''
            : ' The requester has been emailed.';
    }
}
