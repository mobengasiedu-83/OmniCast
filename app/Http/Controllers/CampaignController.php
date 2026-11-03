<?php

namespace App\Http\Controllers;

use App\Enums\CampaignStatus;
use App\Enums\Channel;
use App\Http\Controllers\Concerns\ResolvesTenantClient;
use App\Http\Requests\Campaign\CampaignRequest;
use App\Http\Requests\Campaign\RejectCampaignRequest;
use App\Models\CallerId;
use App\Models\Campaign;
use App\Models\ContactList;
use App\Models\SenderId;
use App\Services\CampaignService;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CampaignController extends Controller
{
    use ResolvesTenantClient;

    public function __construct(
        protected CampaignService $campaigns,
        protected WalletService $wallets,
    ) {}

    /**
     * List the campaigns of the client tenant.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Campaign::class);

        $filters = [
            'status' => $request->string('status')->toString(),
            'channel' => $request->string('channel')->toString(),
            'search' => $request->string('search')->toString(),
        ];

        $campaigns = Campaign::query()
            ->forUser($request->user())
            ->with(['senderId:id,name', 'contactList:id,name', 'client:id,name'])
            ->when($filters['status'] !== '', fn ($query) => $query->where('status', $filters['status']))
            ->when($filters['channel'] !== '', fn ($query) => $query->where('channel', $filters['channel']))
            ->when($filters['search'] !== '', fn ($query) => $query->where(function ($query) use ($filters): void {
                $query->where('name', 'like', '%'.$filters['search'].'%')
                    ->orWhere('reference', 'like', '%'.$filters['search'].'%');
            }))
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Campaign $campaign): array => $this->summary($campaign));

        return Inertia::render('campaigns/index', [
            'campaigns' => $campaigns,
            'filters' => $filters,
            'statuses' => $this->statusOptions(),
            'channels' => $this->channelOptions(),
            'can' => [
                'create' => Gate::allows('create', Campaign::class),
            ],
        ]);
    }

    /**
     * Show the campaign wizard.
     */
    public function create(Request $request): Response
    {
        Gate::authorize('create', Campaign::class);

        return Inertia::render('campaigns/create', [
            ...$this->formOptions($request),
            'campaign' => null,
        ]);
    }

    /**
     * Persist a new draft campaign.
     */
    public function store(CampaignRequest $request): RedirectResponse
    {
        $campaign = $this->campaigns->create($request->campaignAttributes(), $request->user());

        return to_route('campaigns.show', $campaign)->with('toast', [
            'type' => 'success',
            'message' => "Campaign {$campaign->reference} saved as a draft.",
        ]);
    }

    /**
     * Show a campaign with its delivery breakdown.
     */
    public function show(Campaign $campaign): Response
    {
        Gate::authorize('view', $campaign);

        $campaign->load([
            'client:id,name,timezone',
            'contactList:id,name',
            'senderId:id,name',
            'callerId:id,number,label',
            'creator:id,name',
            'approver:id,name',
        ]);

        $statusCounts = $campaign->campaignContacts()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $recipients = $campaign->campaignContacts()
            ->with('contact:id,first_name,last_name,phone')
            ->orderBy('id')
            ->paginate(20)
            ->through(fn ($campaignContact): array => [
                'id' => $campaignContact->id,
                'name' => $campaignContact->contact?->full_name,
                'phone' => $campaignContact->contact?->phone,
                'status' => $campaignContact->status->value,
                'status_label' => $campaignContact->status->label(),
                'attempts' => $campaignContact->attempts,
                'cost' => (float) $campaignContact->cost,
                'error_message' => $campaignContact->error_message,
                'sent_at' => $campaignContact->sent_at?->toIso8601String(),
            ]);

        return Inertia::render('campaigns/show', [
            'campaign' => [
                ...$this->summary($campaign),
                'sms_message' => $campaign->sms_message,
                'voice_script' => $campaign->voice_script,
                'voice_recording_path' => $campaign->voice_recording_path,
                'voice_max_duration_seconds' => $campaign->voice_max_duration_seconds,
                'timezone' => $campaign->timezone,
                'schedule_type' => $campaign->schedule_type,
                'max_retries' => $campaign->max_retries,
                'retry_delay_minutes' => $campaign->retry_delay_minutes,
                'notes' => $campaign->notes,
                'rejection_reason' => $campaign->rejection_reason,
                'creator' => $campaign->creator?->name,
                'approver' => $campaign->approver?->name,
                'caller' => $campaign->callerId?->number,
                'submitted_at' => $campaign->submitted_at?->toIso8601String(),
                'approved_at' => $campaign->approved_at?->toIso8601String(),
                'launched_at' => $campaign->launched_at?->toIso8601String(),
                'completed_at' => $campaign->completed_at?->toIso8601String(),
            ],
            'statusCounts' => $statusCounts,
            'recipients' => $recipients,
            'abilities' => [
                'update' => Gate::allows('update', $campaign),
                'submit' => Gate::allows('submit', $campaign),
                'approve' => Gate::allows('approve', $campaign),
                'launch' => Gate::allows('launch', $campaign),
                'cancel' => Gate::allows('cancel', $campaign),
                'delete' => Gate::allows('delete', $campaign),
            ],
        ]);
    }

    /**
     * Show the campaign edit form.
     */
    public function edit(Request $request, Campaign $campaign): Response
    {
        Gate::authorize('update', $campaign);

        return Inertia::render('campaigns/edit', [
            ...$this->formOptions($request),
            'campaign' => [
                'id' => $campaign->id,
                'reference' => $campaign->reference,
                'name' => $campaign->name,
                'channel' => $campaign->channel->value,
                'contact_list_id' => $campaign->contact_list_id,
                'sender_id' => $campaign->sender_id,
                'caller_id' => $campaign->caller_id,
                'sms_message' => $campaign->sms_message,
                'voice_script' => $campaign->voice_script,
                'voice_recording_path' => $campaign->voice_recording_path,
                'voice_max_duration_seconds' => $campaign->voice_max_duration_seconds,
                'schedule_type' => $campaign->schedule_type,
                'scheduled_at' => $campaign->scheduled_at?->format('Y-m-d\TH:i'),
                'timezone' => $campaign->timezone,
                'max_retries' => $campaign->max_retries,
                'retry_delay_minutes' => $campaign->retry_delay_minutes,
                'notes' => $campaign->notes,
            ],
        ]);
    }

    /**
     * Update an editable campaign and reprice it.
     */
    public function update(CampaignRequest $request, Campaign $campaign): RedirectResponse
    {
        Gate::authorize('update', $campaign);

        $this->campaigns->update($campaign, $request->campaignAttributes());

        return to_route('campaigns.show', $campaign)->with('toast', [
            'type' => 'success',
            'message' => 'Campaign updated and re-priced.',
        ]);
    }

    /**
     * Delete a draft campaign.
     */
    public function destroy(Campaign $campaign): RedirectResponse
    {
        Gate::authorize('delete', $campaign);

        $campaign->delete();

        return to_route('campaigns.index')->with('toast', [
            'type' => 'success',
            'message' => 'Campaign removed from the workspace.',
        ]);
    }

    /**
     * Build the audience and send the campaign for approval.
     */
    public function submit(Request $request, Campaign $campaign): RedirectResponse
    {
        Gate::authorize('submit', $campaign);

        $this->campaigns->submit($campaign, $request->user());

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Campaign submitted for approval. Estimated cost is now reserved.',
        ]);
    }

    /**
     * Approve a campaign waiting for review.
     */
    public function approve(Request $request, Campaign $campaign): RedirectResponse
    {
        Gate::authorize('approve', $campaign);

        $this->campaigns->approve($campaign, $request->user());

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Campaign approved and ready to launch.',
        ]);
    }

    /**
     * Reject a campaign and release its held funds.
     */
    public function reject(RejectCampaignRequest $request, Campaign $campaign): RedirectResponse
    {
        Gate::authorize('approve', $campaign);

        $this->campaigns->reject($campaign, $request->user(), $request->string('reason')->toString());

        return back()->with('toast', [
            'type' => 'warning',
            'message' => 'Campaign rejected and reserved funds released.',
        ]);
    }

    /**
     * Launch an approved campaign now or hand it to the scheduler.
     */
    public function launch(Campaign $campaign): RedirectResponse
    {
        Gate::authorize('launch', $campaign);

        $this->campaigns->launch($campaign);

        return back()->with('toast', [
            'type' => 'success',
            'message' => $campaign->status === CampaignStatus::Scheduled
                ? 'Campaign scheduled for delivery.'
                : 'Campaign launched and dispatching now.',
        ]);
    }

    /**
     * Cancel a campaign.
     */
    public function cancel(Campaign $campaign): RedirectResponse
    {
        Gate::authorize('cancel', $campaign);

        $this->campaigns->cancel($campaign);

        return back()->with('toast', [
            'type' => 'warning',
            'message' => 'Campaign cancelled and held funds released.',
        ]);
    }

    /**
     * Shape a campaign row for the list and detail screens.
     *
     * @return array<string, mixed>
     */
    protected function summary(Campaign $campaign): array
    {
        return [
            'id' => $campaign->id,
            'reference' => $campaign->reference,
            'name' => $campaign->name,
            'client' => $campaign->client?->name,
            'channel' => $campaign->channel->value,
            'channel_label' => $campaign->channel->label(),
            'status' => $campaign->status->value,
            'status_label' => $campaign->status->label(),
            'audience' => $campaign->contactList?->name ?? 'All broadcastable contacts',
            'sender' => $campaign->senderId?->name,
            'estimated_recipients' => $campaign->estimated_recipients,
            'estimated_sms_parts' => $campaign->estimated_sms_parts,
            'estimated_cost' => (float) $campaign->estimated_cost,
            'reserved_amount' => (float) $campaign->reserved_amount,
            'actual_cost' => (float) $campaign->actual_cost,
            'scheduled_at' => $campaign->scheduled_at?->toIso8601String(),
            'created_at' => $campaign->created_at?->toIso8601String(),
        ];
    }

    /**
     * Options shared by the create and edit screens.
     *
     * @return array<string, mixed>
     */
    protected function formOptions(Request $request): array
    {
        $client = $this->tenantClient($request);

        return [
            'clients' => $this->selectableClients($request),
            'tenantClientId' => $client?->id,
            'contactLists' => $client === null ? [] : ContactList::query()
                ->where('client_id', $client->id)
                ->withCount('contacts')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (ContactList $list): array => [
                    'id' => $list->id,
                    'name' => $list->name,
                    'contacts_count' => $list->contacts_count,
                ])
                ->all(),
            'senderIds' => $client === null ? [] : SenderId::query()
                ->where('client_id', $client->id)
                ->where('status', 'approved')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->all(),
            'callerIds' => $client === null ? [] : CallerId::query()
                ->where('client_id', $client->id)
                ->where('status', 'approved')
                ->orderBy('number')
                ->get(['id', 'number', 'label'])
                ->all(),
            'channels' => $this->channelOptions(),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    protected function statusOptions(): array
    {
        return array_map(
            fn (CampaignStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
            CampaignStatus::cases(),
        );
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    protected function channelOptions(): array
    {
        return array_map(
            fn (Channel $channel): array => ['value' => $channel->value, 'label' => $channel->label()],
            Channel::cases(),
        );
    }
}
