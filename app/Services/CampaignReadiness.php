<?php

namespace App\Services;

use App\Enums\CampaignAudioStatus;
use App\Enums\Channel;
use App\Enums\SenderIdChannel;
use App\Enums\SenderIdStatus;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\SenderId;
use Illuminate\Validation\ValidationException;

/**
 * Decides whether a campaign has the approved assets it needs before it may run.
 *
 * SMS and email campaigns are only dialled out under a sender ID the tenant owns and
 * the platform has approved for that channel. No campaign is ever sent under a shared
 * platform identity, so this gate cannot be waived by configuration. Voice campaigns
 * never carry their own caller ID:
 * every campaign dials from the single platform DID, so the approval that matters
 * for voice is the review of the audio (recording or script) that will be played.
 *
 * Campaign-level approval is deliberately not part of readiness: once the assets
 * above are approved the campaign may run without a reviewer signing it off.
 */
class CampaignReadiness
{
    public function __construct(protected PlatformConfiguration $configuration) {}

    /**
     * Everything standing between the campaign and delivery.
     *
     * @return list<array{key: string, label: string, detail: string}>
     */
    public function issues(Campaign $campaign, bool $includeAudio = true): array
    {
        $campaign->loadMissing(['senderId', 'client']);

        $issues = [];

        foreach ($this->requiredSenderChannels($campaign) as $channel) {
            $issue = $this->senderIssue($campaign, $channel);

            if ($issue !== null) {
                $issues[] = $issue;
            }
        }

        if ($includeAudio && $campaign->requiresAudioApproval() && $this->configuration->audioApprovalRequired($campaign->client)) {
            $issue = $this->audioIssue($campaign);

            if ($issue !== null) {
                $issues[] = $issue;
            }
        }

        if ($campaign->channel->requiresVoiceContent() && $this->outboundCallerId($campaign->client) === null) {
            $issues[] = [
                'key' => 'voice_did',
                'label' => 'No outbound caller ID configured',
                'detail' => 'Set voice.outbound_caller_id in platform settings so every voice campaign dials from the platform DID.',
            ];
        }

        return $issues;
    }

    /**
     * Whether the campaign may be launched as it stands.
     */
    public function isReady(Campaign $campaign, bool $includeAudio = true): bool
    {
        return $this->issues($campaign, $includeAudio) === [];
    }

    /**
     * Refuse the action when a governance gate has not been cleared.
     *
     * @throws ValidationException
     */
    public function assertReady(Campaign $campaign, bool $includeAudio = true): void
    {
        $issue = $this->issues($campaign, $includeAudio)[0] ?? null;

        if ($issue === null) {
            return;
        }

        throw ValidationException::withMessages([
            'campaign' => "This campaign cannot run yet: {$issue['label']}. {$issue['detail']}",
        ]);
    }

    /**
     * The sender ID channel a campaign of this type must be approved under.
     */
    public function senderChannelFor(Channel $channel): ?SenderIdChannel
    {
        return match ($channel) {
            Channel::Sms, Channel::MultiChannel => SenderIdChannel::Sms,
            Channel::Email => SenderIdChannel::Email,
            Channel::Voice => null,
        };
    }

    /**
     * The single outbound DID every voice campaign dials from.
     */
    public function outboundCallerId(?Client $client = null): ?string
    {
        $configured = $this->configuration->resolve(
            'voice',
            'outbound_caller_id',
            config('services.voice.outbound_caller_id'),
            $client,
        );

        if (! is_string($configured)) {
            return null;
        }

        $number = trim($configured);

        return $number === '' ? null : $number;
    }

    /**
     * The sender ID channels the campaign has to be approved under.
     *
     * @return list<SenderIdChannel>
     */
    protected function requiredSenderChannels(Campaign $campaign): array
    {
        $channel = $this->senderChannelFor($campaign->channel);

        return $channel === null ? [] : [$channel];
    }

    /**
     * @return array{key: string, label: string, detail: string}|null
     */
    protected function senderIssue(Campaign $campaign, SenderIdChannel $channel): ?array
    {
        $senderId = $campaign->senderId;

        if ($senderId === null) {
            return [
                'key' => 'sender_id',
                'label' => 'No '.$channel->label().' sender ID selected',
                'detail' => 'Choose an approved '.$channel->label().' sender ID on the campaign before it runs.',
            ];
        }

        $senderClientId = $this->senderClientId($senderId);

        if ($senderClientId !== null && $senderClientId !== (int) $campaign->client_id) {
            return [
                'key' => 'sender_client',
                'label' => 'Sender ID belongs to another client',
                'detail' => "The sender ID {$senderId->name} is not issued to this client, so the campaign cannot send under it.",
            ];
        }

        if ($senderId->channel !== $channel) {
            return [
                'key' => 'sender_channel',
                'label' => 'Sender ID is issued for '.$senderId->channel->label(),
                'detail' => 'A '.$channel->label().' campaign needs a sender ID approved for '.$channel->label().'.',
            ];
        }

        if ($senderId->status !== SenderIdStatus::Approved) {
            return [
                'key' => 'sender_status',
                'label' => 'Sender ID '.strtolower($senderId->status->label()),
                'detail' => "The sender ID {$senderId->name} must be approved for {$channel->label()} before this campaign can run.",
            ];
        }

        return null;
    }

    /**
     * The client the sender ID was issued to.
     *
     * Callers eager load the relation with a column subset, so `client_id` can be
     * absent from the loaded model. Read it from the table in that case rather than
     * treating a missing column as a mismatched owner.
     */
    protected function senderClientId(SenderId $senderId): ?int
    {
        $clientId = $senderId->getAttribute('client_id');

        if ($clientId !== null) {
            return (int) $clientId;
        }

        $resolved = SenderId::query()->whereKey($senderId->getKey())->value('client_id');

        return $resolved === null ? null : (int) $resolved;
    }

    /**
     * @return array{key: string, label: string, detail: string}|null
     */
    protected function audioIssue(Campaign $campaign): ?array
    {
        if ($campaign->audio_status === CampaignAudioStatus::Approved) {
            return null;
        }

        $detail = $campaign->audio_status === CampaignAudioStatus::Rejected
            ? 'The audio was rejected: '.($campaign->audio_rejection_reason ?? 'the reviewer left no reason').'. Edit the campaign and submit it again.'
            : 'A reviewer must approve the recording or script before this campaign can run.';

        return [
            'key' => 'audio',
            'label' => 'Voice audio '.strtolower($campaign->audio_status->label()),
            'detail' => $detail,
        ];
    }
}
