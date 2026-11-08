<?php

namespace App\Services\Webhooks;

use App\Enums\WebhookEvent;
use App\Models\Campaign;
use App\Models\EmailMessage;
use App\Models\SmppMessage;
use App\Models\SmsMessage;
use Illuminate\Support\Str;

/**
 * Builds the frozen body of a webhook callback.
 *
 * The envelope is Stripe-shaped on purpose: `id`, `type`, `version`,
 * `created_at` and a `data` object, so an integration written against a
 * familiar contract can be pointed at OmniCast without a translation layer.
 */
class WebhookPayload
{
    /**
     * The envelope every event shares.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function envelope(WebhookEvent $event, array $data): array
    {
        return [
            'id' => 'evt_'.Str::ulid(),
            'type' => $event->value,
            'version' => $event->version(),
            'created_at' => now()->toIso8601String(),
            'data' => $data,
        ];
    }

    /**
     * The body of a campaign event.
     *
     * @return array<string, mixed>
     */
    public function forCampaign(WebhookEvent $event, Campaign $campaign): array
    {
        $campaign->loadMissing('client');

        return $this->envelope($event, [
            'id' => $campaign->id,
            'reference' => $campaign->reference,
            'name' => $campaign->name,
            'channel' => $campaign->channel->value,
            'status' => $campaign->status->value,
            'client' => [
                'id' => $campaign->client_id,
                'name' => $campaign->client?->name,
            ],
            'audience' => [
                'estimated_recipients' => $campaign->estimated_recipients,
                'estimated_sms_parts' => $campaign->estimated_sms_parts,
            ],
            'cost' => [
                'estimated' => (float) $campaign->estimated_cost,
                'actual' => (float) $campaign->actual_cost,
                'currency' => (string) config('billing.currency', 'GHS'),
            ],
            'schedule' => [
                'type' => $campaign->schedule_type,
                'scheduled_at' => $campaign->scheduled_at?->toIso8601String(),
                'timezone' => $campaign->timezone,
            ],
            'rejection_reason' => $campaign->rejection_reason,
            'timestamps' => [
                'created_at' => $campaign->created_at?->toIso8601String(),
                'submitted_at' => $campaign->submitted_at?->toIso8601String(),
                'approved_at' => $campaign->approved_at?->toIso8601String(),
                'rejected_at' => $campaign->rejected_at?->toIso8601String(),
                'launched_at' => $campaign->launched_at?->toIso8601String(),
                'completed_at' => $campaign->completed_at?->toIso8601String(),
            ],
            'links' => [
                'self' => route('api.v1.campaigns.show', ['campaign' => $campaign->id]),
            ],
        ]);
    }

    /**
     * The body of an SMS receipt event.
     *
     * @return array<string, mixed>
     */
    public function forSms(WebhookEvent $event, SmsMessage $message): array
    {
        $message->loadMissing('campaign');

        return $this->envelope($event, [
            'reference' => $message->reference,
            'status' => $message->status->value,
            'provider' => $message->provider,
            'provider_message_id' => $message->provider_message_id,
            'from' => $message->from_number,
            'to' => $message->to_number,
            'segments' => $message->segments,
            'cost' => (float) $message->cost,
            'error' => $message->error_message,
            'campaign' => $message->campaign === null ? null : [
                'id' => $message->campaign->id,
                'reference' => $message->campaign->reference,
            ],
            'timestamps' => [
                'submitted_at' => $message->submitted_at?->toIso8601String(),
                'delivered_at' => $message->delivered_at?->toIso8601String(),
                'failed_at' => $message->failed_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * The body of an email receipt event.
     *
     * @return array<string, mixed>
     */
    public function forEmail(WebhookEvent $event, EmailMessage $message): array
    {
        $message->loadMissing('campaign');

        return $this->envelope($event, [
            'reference' => $message->reference,
            'status' => $message->status->value,
            'provider' => $message->provider,
            'provider_message_id' => $message->provider_message_id,
            'from' => $message->from_address,
            'to' => $message->to_address,
            'subject' => $message->subject,
            'cost' => (float) $message->cost,
            'error' => $message->error_message,
            'campaign' => $message->campaign === null ? null : [
                'id' => $message->campaign->id,
                'reference' => $message->campaign->reference,
            ],
            'timestamps' => [
                'submitted_at' => $message->submitted_at?->toIso8601String(),
                'delivered_at' => $message->delivered_at?->toIso8601String(),
                'failed_at' => $message->failed_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * The body of an SMPP submission event.
     *
     * @return array<string, mixed>
     */
    public function forSmpp(WebhookEvent $event, SmppMessage $message): array
    {
        $message->loadMissing('account');

        return $this->envelope($event, [
            'reference' => $message->reference,
            'message_id' => $message->message_id,
            'status' => $message->status->value,
            'from' => $message->source_address,
            'to' => $message->destination_address,
            'segments' => $message->segments,
            'encoding' => $message->encoding->value,
            'provider' => $message->provider,
            'error' => $message->error_message,
            'smsc' => [
                'account' => $message->account?->name,
                'system_id' => $message->account?->system_id,
            ],
            'timestamps' => [
                'submitted_at' => $message->submitted_at?->toIso8601String(),
                'delivered_at' => $message->delivered_at?->toIso8601String(),
                'failed_at' => $message->failed_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * The body of a test event.
     *
     * @return array<string, mixed>
     */
    public function forTest(WebhookEvent $event = WebhookEvent::CampaignCreated): array
    {
        return $this->envelope($event, [
            'test' => true,
            'message' => 'This is a test event from OmniCast.',
            'links' => ['docs' => url('/docs/webhooks')],
        ]);
    }
}
