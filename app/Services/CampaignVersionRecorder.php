<?php

namespace App\Services;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\CampaignVersion;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Freezes what a campaign will deliver so an approval cannot silently drift.
 */
class CampaignVersionRecorder
{
    /**
     * Store the campaign's current content as the next frozen version.
     */
    public function record(Campaign $campaign, CampaignStatus $status, ?User $user = null): CampaignVersion
    {
        $campaign->loadMissing(['senderId', 'callerId']);

        $content = $this->content($campaign);
        $version = ((int) $campaign->versions()->max('version')) + 1;

        $record = CampaignVersion::query()->create([
            'campaign_id' => $campaign->id,
            'version' => $version,
            'status' => $status,
            'fingerprint' => $this->fingerprint($content),
            'snapshot' => [
                ...$content,
                'sender_name' => $campaign->senderId?->name,
                'caller_number' => $campaign->callerId?->number,
                'estimated_sms_parts' => (int) $campaign->estimated_sms_parts,
                'estimated_cost' => (float) $campaign->estimated_cost,
                'reserved_amount' => (float) $campaign->reserved_amount,
            ],
            'audience_size' => (int) $campaign->estimated_recipients,
            'estimated_cost' => $campaign->estimated_cost,
            'created_by' => $user?->id ?? $campaign->created_by,
            'frozen_at' => now(),
        ]);

        $campaign->forceFill(['current_version' => $version])->save();

        return $record;
    }

    /**
     * The most recent approved snapshot, when the campaign has been versioned.
     */
    public function latestApproved(Campaign $campaign): ?CampaignVersion
    {
        return $campaign->versions()
            ->where('status', CampaignStatus::Approved->value)
            ->orderByDesc('version')
            ->first();
    }

    /**
     * Refuse a launch when the campaign content no longer matches its approval.
     *
     * @throws ValidationException
     */
    public function assertMatchesApprovedSnapshot(Campaign $campaign): void
    {
        $approved = $this->latestApproved($campaign);

        if ($approved === null) {
            return;
        }

        $current = $campaign->fresh();

        if ($current === null) {
            return;
        }

        if (hash_equals($approved->fingerprint, $this->fingerprint($this->content($current)))) {
            return;
        }

        throw ValidationException::withMessages([
            'campaign' => 'This campaign changed after it was approved. Submit it again for a new review.',
        ]);
    }

    /**
     * The delivery-relevant fields of the campaign, in a stable order.
     *
     * @return array<string, mixed>
     */
    protected function content(Campaign $campaign): array
    {
        return [
            'name' => $campaign->name,
            'channel' => $campaign->channel->value,
            'contact_list_id' => $campaign->contact_list_id,
            'contact_segment_id' => $campaign->contact_segment_id,
            'sender_id' => $campaign->sender_id,
            'caller_id' => $campaign->caller_id,
            'sms_message' => $campaign->sms_message,
            'email_subject' => $campaign->email_subject,
            'email_body' => $campaign->email_body,
            'email_format' => $campaign->email_format?->value,
            'voice_script' => $campaign->voice_script,
            'voice_recording_path' => $campaign->voice_recording_path,
            'voice_max_duration_seconds' => $campaign->voice_max_duration_seconds,
            'ivr_enabled' => (bool) $campaign->ivr_enabled,
            'ivr_prompt' => $campaign->ivr_prompt,
            'ivr_timeout_seconds' => (int) $campaign->ivr_timeout_seconds,
            'ivr_max_attempts' => (int) $campaign->ivr_max_attempts,
            'ivr_options' => $campaign->ivr_options,
            'schedule_type' => $campaign->schedule_type,
            'scheduled_at' => $campaign->scheduled_at?->toIso8601String(),
            'timezone' => $campaign->timezone,
            'max_retries' => $campaign->max_retries,
            'retry_delay_minutes' => $campaign->retry_delay_minutes,
            'notes' => $campaign->notes,
            'recipients' => (int) $campaign->estimated_recipients,
        ];
    }

    /**
     * Hash the delivery content so later edits become detectable.
     *
     * @param  array<string, mixed>  $content
     */
    protected function fingerprint(array $content): string
    {
        return hash('sha256', json_encode($content, JSON_THROW_ON_ERROR));
    }
}
