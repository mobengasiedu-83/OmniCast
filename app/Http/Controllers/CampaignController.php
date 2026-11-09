<?php

namespace App\Http\Controllers;

use App\Enums\CampaignStatus;
use App\Enums\Channel;
use App\Enums\LegalAcceptanceContext;
use App\Enums\LegalDocumentType;
use App\Enums\TemplateStatus;
use App\Http\Controllers\Concerns\PresentsTemplates;
use App\Http\Controllers\Concerns\ResolvesTenantClient;
use App\Http\Requests\Campaign\CampaignRequest;
use App\Http\Requests\Campaign\RejectCampaignRequest;
use App\Models\CallerId;
use App\Models\Campaign;
use App\Models\CampaignVersion;
use App\Models\Client;
use App\Models\ContactList;
use App\Models\ContactSegment;
use App\Models\EmailAttachment;
use App\Models\MessageTemplate;
use App\Models\SenderId;
use App\Models\User;
use App\Services\CampaignReadiness;
use App\Services\CampaignService;
use App\Services\ContactSegmentQuery;
use App\Services\Email\EmailAttachmentStore;
use App\Services\EmailMessageReport;
use App\Services\Legal\LegalLibrary;
use App\Services\ModuleAvailability;
use App\Services\PlatformConfiguration;
use App\Services\SmsMessageReport;
use App\Services\TemplateLibrary;
use App\Services\VoiceCallReport;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CampaignController extends Controller
{
    use PresentsTemplates, ResolvesTenantClient;

    public function __construct(
        protected CampaignService $campaigns,
        protected WalletService $wallets,
        protected CampaignReadiness $readiness,
        protected PlatformConfiguration $configuration,
        protected VoiceCallReport $voiceReport,
        protected SmsMessageReport $smsReport,
        protected EmailMessageReport $emailReport,
        protected TemplateLibrary $library,
        protected ModuleAvailability $modules,
        protected EmailAttachmentStore $attachments,
        protected LegalLibrary $legal,
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
            ->with(['senderId:id,name', 'contactList:id,name', 'contactSegment:id,name', 'client:id,name'])
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
            'channels' => $this->channelOptions($this->tenantClient($request)),
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

        $template = $this->requestedTemplate($request);

        return Inertia::render('campaigns/create', [
            ...$this->formOptions($request),
            'campaign' => null,
            'initial' => $template === null ? [] : [
                'channel' => $template->channel->value,
                'message_template_id' => (string) $template->id,
                ...$this->templateCopy($template),
            ],
        ]);
    }

    /**
     * Persist a new draft campaign.
     */
    public function store(CampaignRequest $request): RedirectResponse
    {
        $campaign = $this->campaigns->create($request->campaignAttributes(), $request->user());

        if ($campaign->wasRecentlyCreated) {
            $this->library->recordCampaignUsage($campaign);
            $this->attachments->sync($campaign, $request->attachmentFiles(), [], $request->user());
        }

        return to_route('campaigns.show', $campaign)->with('toast', [
            'type' => $campaign->wasRecentlyCreated ? 'success' : 'warning',
            'message' => $campaign->wasRecentlyCreated
                ? "Campaign {$campaign->reference} saved as a draft."
                : "Campaign {$campaign->reference} was already saved for this request.",
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
            'senderId:id,client_id,name,channel,status',
            'callerId:id,number,label',
            'creator:id,name',
            'approver:id,name',
            'audioReviewer:id,name',
            'messageTemplate:id,name,channel',
            'emailAttachments',
        ]);

        $statusCounts = $campaign->campaignContacts()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $recipients = $campaign->campaignContacts()
            ->with('contact:id,first_name,last_name,phone,email')
            ->orderBy('id')
            ->paginate(20)
            ->through(fn ($campaignContact): array => [
                'id' => $campaignContact->id,
                'name' => $campaignContact->contact?->full_name,
                'phone' => $campaignContact->contact?->phone,
                'email' => $campaignContact->contact?->email,
                'status' => $campaignContact->status->value,
                'status_label' => $campaignContact->status->label(),
                'attempts' => $campaignContact->attempts,
                'cost' => (float) $campaignContact->cost,
                'error_message' => $campaignContact->error_message,
                'sent_at' => $campaignContact->sent_at?->toIso8601String(),
            ]);

        $versions = $campaign->versions()
            ->with('author:id,name')
            ->orderByDesc('version')
            ->get()
            ->map(fn (CampaignVersion $version): array => [
                'id' => $version->id,
                'version' => $version->version,
                'status' => $version->status->value,
                'status_label' => $version->status->label(),
                'audience_size' => $version->audience_size,
                'estimated_cost' => (float) $version->estimated_cost,
                'fingerprint' => $version->fingerprint,
                'frozen_at' => $version->frozen_at?->toIso8601String(),
                'author' => $version->author?->name,
            ]);

        return Inertia::render('campaigns/show', [
            'campaign' => [
                ...$this->summary($campaign),
                'sms_message' => $campaign->sms_message,
                'email_subject' => $campaign->email_subject,
                'email_body' => $campaign->email_body,
                'email_format' => $campaign->email_format->value,
                'voice_script' => $campaign->voice_script,
                'email_attachments' => $campaign->emailAttachments
                    ->map(fn (EmailAttachment $attachment): array => $this->attachmentRow($attachment, $campaign))
                    ->all(),
                'template' => $campaign->messageTemplate === null ? null : [
                    'id' => $campaign->messageTemplate->id,
                    'name' => $campaign->messageTemplate->name,
                    'channel' => $campaign->messageTemplate->channel->value,
                ],
                'voice_recording_path' => $campaign->voice_recording_path,
                'voice_max_duration_seconds' => $campaign->voice_max_duration_seconds,
                'ivr_enabled' => (bool) $campaign->ivr_enabled,
                'ivr_prompt' => $campaign->ivr_prompt,
                'ivr_timeout_seconds' => (int) $campaign->ivr_timeout_seconds,
                'ivr_max_attempts' => (int) $campaign->ivr_max_attempts,
                'ivr_options' => $campaign->ivr_options ?? [],
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
            'versions' => $versions,
            'recipients' => $recipients,
            'review' => [
                'requires_audio' => $campaign->requiresAudioApproval(),
                'requires_campaign_approval' => $this->configuration->campaignApprovalRequired($campaign->client),
                'audio_status' => $campaign->audio_status->value,
                'audio_status_label' => $campaign->audio_status->label(),
                'audio_rejection_reason' => $campaign->audio_rejection_reason,
                'audio_reviewed_at' => $campaign->audio_reviewed_at?->toIso8601String(),
                'audio_reviewer' => $campaign->audioReviewer?->name,
                'outbound_caller' => $this->readiness->outboundCallerId($campaign->client),
                'blockers' => $this->readiness->issues($campaign),
            ],
            'voiceReport' => $campaign->channel->requiresVoiceContent()
                ? $this->voiceReport->forCampaign($campaign, 8)
                : null,
            'smsReport' => $campaign->channel->requiresSmsContent()
                ? $this->smsReport->forCampaign($campaign, 8)
                : null,
            'emailReport' => $campaign->channel->requiresEmailContent()
                ? $this->emailReport->forCampaign($campaign, 8)
                : null,
            'acceptableUse' => $this->legal->notice(LegalDocumentType::AcceptableUse),
            'abilities' => [
                'update' => Gate::allows('update', $campaign),
                'submit' => Gate::allows('submit', $campaign),
                'approve' => Gate::allows('approve', $campaign),
                'review_audio' => Gate::allows('reviewAudio', $campaign),
                'launch' => Gate::allows('launch', $campaign),
                'cancel' => Gate::allows('cancel', $campaign),
                'delete' => Gate::allows('delete', $campaign),
                'duplicate' => Gate::allows('duplicate', $campaign),
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
                'contact_segment_id' => $campaign->contact_segment_id,
                'sender_id' => $campaign->sender_id,
                'caller_id' => $campaign->caller_id,
                'message_template_id' => $campaign->message_template_id,
                'sms_message' => $campaign->sms_message,
                'email_subject' => $campaign->email_subject,
                'email_body' => $campaign->email_body,
                'email_format' => $campaign->email_format->value,
                'email_attachments' => $campaign->emailAttachments()
                    ->get()
                    ->map(fn (EmailAttachment $attachment): array => $this->attachmentRow($attachment, $campaign))
                    ->all(),
                'voice_script' => $campaign->voice_script,
                'voice_recording_path' => $campaign->voice_recording_path,
                'voice_max_duration_seconds' => $campaign->voice_max_duration_seconds,
                'ivr_enabled' => (bool) $campaign->ivr_enabled,
                'ivr_prompt' => $campaign->ivr_prompt,
                'ivr_timeout_seconds' => $campaign->ivr_timeout_seconds,
                'ivr_max_attempts' => $campaign->ivr_max_attempts,
                'ivr_options' => $campaign->ivr_options ?? [],
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

        $previousTemplateId = $campaign->message_template_id;

        $this->campaigns->update($campaign, $request->campaignAttributes());

        $this->attachments->sync(
            $campaign,
            $request->attachmentFiles(),
            $request->removedAttachmentIds(),
            $request->user(),
        );

        if ($campaign->message_template_id !== $previousTemplateId) {
            $this->library->recordCampaignUsage($campaign);
        }

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
     * Build the audience, hold the funds and either approve the campaign
     * automatically or send it for review.
     */
    public function submit(Request $request, Campaign $campaign): RedirectResponse
    {
        Gate::authorize('submit', $campaign);

        $this->recordAcceptableUse($request);

        $campaign = $this->campaigns->submit($campaign, $request->user());

        return back()->with('toast', [
            'type' => 'success',
            'message' => $campaign->status === CampaignStatus::Approved
                ? 'Campaign approved automatically. Estimated cost is now reserved and it is ready to launch.'
                : 'Campaign submitted for review. Estimated cost is now reserved.',
        ]);
    }

    /**
     * Record the submitter's acknowledgement of the Acceptable Use Policy.
     *
     * The submit button is the confirmation: the campaign show page prints the
     * notice that links to the policy directly above it, so pressing it is the
     * statement that the audience may lawfully be contacted. The evidence names
     * the live version of the policy, so a later revision does not silently
     * change what this submission agreed to.
     */
    protected function recordAcceptableUse(Request $request): void
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return;
        }

        $document = $this->legal->documentOfType(LegalDocumentType::AcceptableUse);

        if ($document === null) {
            return;
        }

        $this->legal->recordAcceptance(
            $user,
            $document,
            LegalAcceptanceContext::CampaignSubmission,
            $request->ip(),
            $request->userAgent(),
        );
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
     * Approve the audio a voice campaign will play.
     */
    public function approveAudio(Request $request, Campaign $campaign): RedirectResponse
    {
        Gate::authorize('reviewAudio', $campaign);

        $campaign = $this->campaigns->approveAudio($campaign, $request->user());

        return back()->with('toast', [
            'type' => 'success',
            'message' => $campaign->status === CampaignStatus::Approved
                ? 'Voice audio approved. The campaign is approved and ready to launch.'
                : 'Voice audio approved. The campaign can now be approved for delivery.',
        ]);
    }

    /**
     * Reject the voice audio with a reason the tenant can act on.
     */
    public function rejectAudio(Request $request, Campaign $campaign): RedirectResponse
    {
        Gate::authorize('reviewAudio', $campaign);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $this->campaigns->rejectAudio($campaign, $request->user(), $validated['reason']);

        return back()->with('toast', [
            'type' => 'warning',
            'message' => 'Voice audio rejected. The tenant can edit and submit the campaign again.',
        ]);
    }

    /**
     * Launch an approved campaign now or hand it to the scheduler.
     */
    public function launch(Campaign $campaign): RedirectResponse
    {
        Gate::authorize('launch', $campaign);

        $campaign = $this->campaigns->launch($campaign);

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
     * Copy a campaign into a new editable draft.
     */
    public function duplicate(Request $request, Campaign $campaign): RedirectResponse
    {
        Gate::authorize('duplicate', $campaign);

        $copy = $this->campaigns->duplicate($campaign, $request->user());

        return to_route('campaigns.show', $copy)->with('toast', [
            'type' => 'success',
            'message' => "Campaign copied into draft {$copy->reference}.",
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
            'audience' => $campaign->contactSegment?->name ?? $campaign->contactList?->name ?? 'All broadcastable contacts',
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
     * The template the editor was opened from, when the user clicked "Use
     * template" in the library.
     */
    protected function requestedTemplate(Request $request): ?MessageTemplate
    {
        $templateId = $request->integer('template');

        if ($templateId <= 0) {
            return null;
        }

        return MessageTemplate::query()
            ->visibleTo($this->tenantClient($request)?->id)
            ->whereIn('status', [TemplateStatus::Draft->value, TemplateStatus::Active->value])
            ->find($templateId);
    }

    /**
     * The copy a picked template puts into the campaign body.
     *
     * @return array<string, string>
     */
    protected function templateCopy(MessageTemplate $template): array
    {
        return match ($template->channel) {
            Channel::Voice => ['voice_script' => $template->body],
            Channel::Email => [
                'email_subject' => $template->subject ?? '',
                'email_body' => $template->body,
                'email_format' => $template->email_format->value,
            ],
            default => ['sms_message' => $template->body],
        };
    }

    /**
     * One attachment as the campaign screens read it.
     *
     * @return array<string, mixed>
     */
    protected function attachmentRow(EmailAttachment $attachment, Campaign $campaign): array
    {
        return [
            'id' => $attachment->id,
            'name' => $attachment->original_name,
            'size' => $attachment->humanSize(),
            'mime_type' => $attachment->mime_type,
            'is_image' => $attachment->isImage(),
            'download_url' => route('campaigns.attachments.download', [
                'campaign' => $campaign->id,
                'attachment' => $attachment->id,
            ]),
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
            'acceptableUse' => $this->legal->notice(LegalDocumentType::AcceptableUse),
            'clients' => $this->selectableClients($request),
            'tenantClientId' => $client?->id,
            'templates' => MessageTemplate::query()
                ->visibleTo($client?->id)
                ->whereIn('status', [TemplateStatus::Draft->value, TemplateStatus::Active->value])
                ->with('category:id,name,slug')
                ->orderBy('name')
                ->get()
                ->map(fn (MessageTemplate $template): array => $this->templatePickerRow($template))
                ->all(),
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
            'contactSegments' => $client === null ? [] : ContactSegment::query()
                ->where('client_id', $client->id)->orderBy('name')->get()
                ->map(fn (ContactSegment $segment): array => [
                    'id' => $segment->id,
                    'name' => $segment->name,
                    'contacts_count' => app(ContactSegmentQuery::class)->count($segment),
                ])->all(),
            'senderIds' => $client === null ? [] : SenderId::query()
                ->where('client_id', $client->id)
                ->where('status', 'approved')
                ->orderBy('name')
                ->get(['id', 'name', 'channel'])
                ->map(fn (SenderId $senderId): array => [
                    'id' => $senderId->id,
                    'name' => $senderId->name,
                    'channel' => $senderId->channel->value,
                ])
                ->all(),
            'callerIds' => $client === null ? [] : CallerId::query()
                ->where('client_id', $client->id)
                ->where('status', 'approved')
                ->orderBy('number')
                ->get(['id', 'number', 'label'])
                ->all(),
            'channels' => $this->channelOptions($client),
            'outboundCallerId' => $this->readiness->outboundCallerId($client),
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
     * The channels this tenant may still pick from.
     *
     * A switched-off module never reaches the wizard, so a tenant can only
     * start a campaign on something they can actually deliver.
     *
     * @return list<array{value: string, label: string}>
     */
    protected function channelOptions(?Client $client = null): array
    {
        return $this->modules->channelOptions($client);
    }
}
