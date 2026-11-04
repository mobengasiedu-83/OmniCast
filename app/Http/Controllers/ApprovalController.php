<?php

namespace App\Http\Controllers;

use App\Enums\Channel;
use App\Enums\Permission;
use App\Enums\SenderIdChannel;
use App\Enums\SenderIdStatus;
use App\Models\Campaign;
use App\Models\SenderId;
use App\Models\User;
use App\Services\CampaignReadiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The platform review desk: every queue that needs a decision before delivery.
 */
class ApprovalController extends Controller
{
    /**
     * The maximum number of rows shown per queue before the filtered index takes over.
     */
    protected const QUEUE_LIMIT = 25;

    public function __construct(protected CampaignReadiness $readiness) {}

    /**
     * Show the campaign, voice audio and sender ID review queues.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $canReviewCampaigns = $user->can(Permission::ApproveCampaigns->value);
        $canReviewSenderIds = $user->can(Permission::ApproveSenderIds->value);

        abort_unless($canReviewCampaigns || $canReviewSenderIds, 403);

        $channel = $request->string('channel')->toString();

        return Inertia::render('approvals/index', [
            'queues' => [
                'campaigns' => $canReviewCampaigns ? $this->campaignQueue($user, $channel) : null,
                'audio' => $canReviewCampaigns ? $this->audioQueue($user) : null,
                'sender_ids' => $canReviewSenderIds ? $this->senderQueue($user, $channel) : null,
            ],
            'filters' => [
                'channel' => $channel,
            ],
            'campaignChannels' => $this->campaignChannelOptions(),
            'senderChannels' => $this->senderChannelOptions(),
            'outboundCallerId' => $this->readiness->outboundCallerId(),
            'abilities' => [
                'campaigns' => $canReviewCampaigns,
                'sender_ids' => $canReviewSenderIds,
            ],
        ]);
    }

    /**
     * Campaigns waiting for an approval decision.
     *
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    protected function campaignQueue(User $user, string $channel): array
    {
        $query = Campaign::query()
            ->awaitingApproval()
            ->forUser($user)
            ->with([
                'client:id,name',
                'contactList:id,name',
                'contactSegment:id,name',
                'senderId:id,name,channel,status',
            ])
            ->when($channel !== '', fn (Builder $builder) => $builder->where('channel', $channel));

        return [
            'total' => (clone $query)->count(),
            'items' => $query->orderBy('submitted_at')
                ->limit(self::QUEUE_LIMIT)
                ->get()
                ->map(fn (Campaign $campaign): array => [
                    'id' => $campaign->id,
                    'reference' => $campaign->reference,
                    'name' => $campaign->name,
                    'client' => $campaign->client?->name,
                    'channel' => $campaign->channel->value,
                    'channel_label' => $campaign->channel->label(),
                    'audience' => $campaign->contactSegment?->name
                        ?? $campaign->contactList?->name
                        ?? 'All broadcastable contacts',
                    'sender' => $campaign->senderId?->name,
                    'recipients' => $campaign->estimated_recipients,
                    'estimated_cost' => (float) $campaign->estimated_cost,
                    'submitted_at' => $campaign->submitted_at?->toIso8601String(),
                    'blockers' => array_map(
                        fn (array $issue): string => $issue['label'],
                        $this->readiness->issues($campaign),
                    ),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Voice campaigns whose audio still needs a reviewer.
     *
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    protected function audioQueue(User $user): array
    {
        $query = Campaign::query()
            ->awaitingApproval()
            ->awaitingAudioReview()
            ->forUser($user)
            ->with(['client:id,name', 'senderId:id,name,channel,status']);

        return [
            'total' => (clone $query)->count(),
            'items' => $query->orderBy('submitted_at')
                ->limit(self::QUEUE_LIMIT)
                ->get()
                ->map(fn (Campaign $campaign): array => [
                    'id' => $campaign->id,
                    'reference' => $campaign->reference,
                    'name' => $campaign->name,
                    'client' => $campaign->client?->name,
                    'channel_label' => $campaign->channel->label(),
                    'voice_script' => $campaign->voice_script,
                    'voice_recording_path' => $campaign->voice_recording_path,
                    'voice_max_duration_seconds' => $campaign->voice_max_duration_seconds,
                    'submitted_at' => $campaign->submitted_at?->toIso8601String(),
                    'blockers' => array_values(array_map(
                        fn (array $issue): string => $issue['label'],
                        array_filter(
                            $this->readiness->issues($campaign),
                            fn (array $issue): bool => $issue['key'] !== 'audio',
                        ),
                    )),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Sender ID requests waiting for an approval decision.
     *
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    protected function senderQueue(User $user, string $channel): array
    {
        $query = SenderId::query()
            ->forUser($user)
            ->whereIn('status', [
                SenderIdStatus::Pending->value,
                SenderIdStatus::Submitted->value,
            ])
            ->with(['client:id,name', 'requester:id,name'])
            ->when($channel !== '', fn (Builder $builder) => $builder->where('channel', $channel));

        return [
            'total' => (clone $query)->count(),
            'items' => $query->orderBy('requested_at')
                ->limit(self::QUEUE_LIMIT)
                ->get()
                ->map(fn (SenderId $senderId): array => [
                    'id' => $senderId->id,
                    'name' => $senderId->name,
                    'channel' => $senderId->channel->value,
                    'channel_label' => $senderId->channel->label(),
                    'identifier_label' => $senderId->channel->identifierLabel(),
                    'country' => $senderId->country,
                    'purpose' => $senderId->purpose,
                    'client' => $senderId->client?->name,
                    'requester' => $senderId->requester?->name,
                    'requested_at' => $senderId->requested_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    protected function campaignChannelOptions(): array
    {
        return array_map(
            fn (Channel $channel): array => ['value' => $channel->value, 'label' => $channel->label()],
            Channel::cases(),
        );
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    protected function senderChannelOptions(): array
    {
        return array_map(
            fn (SenderIdChannel $channel): array => ['value' => $channel->value, 'label' => $channel->label()],
            SenderIdChannel::cases(),
        );
    }
}
