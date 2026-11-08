<?php

namespace App\Http\Resources;

use App\Models\Campaign;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A campaign as the platform HTTP API returns it.
 *
 * The shape is stable: an integration that reads `status` and `statistics` keeps
 * working when a later version adds a field, which is what the `v1` in the route
 * promises. `links` points at the screens and the record itself, so a webhook
 * subscriber can follow an event straight to the campaign it describes.
 */
class CampaignResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Campaign $campaign */
        $campaign = $this->resource;

        return [
            'id' => $campaign->id,
            'reference' => $campaign->reference,
            'name' => $campaign->name,
            'channel' => $campaign->channel->value,
            'status' => $campaign->status->value,
            'status_label' => $campaign->status->label(),
            'version' => $campaign->current_version,
            'client_id' => $campaign->client_id,
            'audience' => [
                'contact_list_id' => $campaign->contact_list_id,
                'contact_segment_id' => $campaign->contact_segment_id,
                'estimated_recipients' => $campaign->estimated_recipients,
            ],
            'content' => [
                'sms_message' => $campaign->sms_message,
                'sms_encoding' => $campaign->sms_encoding?->value,
                'email_subject' => $campaign->email_subject,
                'voice_script' => $campaign->voice_script,
            ],
            'schedule' => [
                'type' => $campaign->schedule_type,
                'scheduled_at' => $campaign->scheduled_at?->toIso8601String(),
                'timezone' => $campaign->timezone,
                'window_starts_at' => $campaign->window_starts_at,
                'window_ends_at' => $campaign->window_ends_at,
            ],
            'cost' => [
                'estimated' => (float) $campaign->estimated_cost,
                'reserved' => (float) $campaign->reserved_amount,
                'actual' => (float) $campaign->actual_cost,
                'currency' => 'GHS',
            ],
            'statistics' => [
                'recipients' => (int) ($campaign->campaign_contacts_count ?? 0),
                'delivered' => (int) ($campaign->delivered_count ?? 0),
                'failed' => (int) ($campaign->failed_count ?? 0),
            ],
            'timestamps' => [
                'created_at' => $campaign->created_at?->toIso8601String(),
                'submitted_at' => $campaign->submitted_at?->toIso8601String(),
                'approved_at' => $campaign->approved_at?->toIso8601String(),
                'launched_at' => $campaign->launched_at?->toIso8601String(),
                'completed_at' => $campaign->completed_at?->toIso8601String(),
            ],
            'links' => [
                'self' => route('api.v1.campaigns.show', ['campaign' => $campaign->id]),
                'web' => route('campaigns.show', ['campaign' => $campaign->id]),
            ],
        ];
    }
}
