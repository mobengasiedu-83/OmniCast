<?php

namespace App\Services\Webhooks;

use App\Enums\WebhookDeliveryStatus;
use App\Enums\WebhookEvent;
use App\Jobs\DeliverWebhook;
use App\Models\Campaign;
use App\Models\EmailMessage;
use App\Models\SmppMessage;
use App\Models\SmsMessage;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Facades\Log;

/**
 * Fans an event out to the subscriptions that asked for it.
 *
 * Emitting is cheap when nobody is subscribed (one indexed query, no rows), so
 * the delivery path can call it without a guard around every transition.
 */
class WebhookDispatcher
{
    public function __construct(
        protected WebhookPayload $payload,
        protected WebhookSender $sender,
    ) {}

    /**
     * Whether outbound callbacks are switched on at all.
     */
    public function enabled(): bool
    {
        return (bool) config('webhooks.enabled', true);
    }

    /**
     * Deliver an event to every matching subscription.
     *
     * @param  array<string, mixed>  $payload
     * @return int the number of deliveries queued
     */
    public function emit(WebhookEvent $event, array $payload, ?int $clientId): int
    {
        if (! $this->enabled()) {
            return 0;
        }

        $endpoints = WebhookEndpoint::query()
            ->subscribingTo($event)
            ->where(function ($query) use ($clientId): void {
                $query->whereNull('client_id');

                if ($clientId !== null) {
                    $query->orWhere('client_id', $clientId);
                }
            })
            ->get();

        $queued = 0;

        foreach ($endpoints as $endpoint) {
            $delivery = WebhookDelivery::query()->create([
                'webhook_endpoint_id' => $endpoint->id,
                'client_id' => $endpoint->client_id ?? $clientId,
                'event' => $event,
                'payload' => $payload,
                'status' => WebhookDeliveryStatus::Pending,
                'attempts' => 0,
            ]);

            DeliverWebhook::dispatch($delivery->id)->onQueue((string) config('webhooks.queue', 'default'));

            $queued++;
        }

        return $queued;
    }

    /**
     * Emit a campaign lifecycle event.
     */
    public function campaign(WebhookEvent $event, Campaign $campaign): int
    {
        return $this->emit($event, $this->payload->forCampaign($event, $campaign), $campaign->client_id);
    }

    /**
     * Emit an SMS receipt event.
     */
    public function sms(WebhookEvent $event, SmsMessage $message): int
    {
        return $this->emit($event, $this->payload->forSms($event, $message), $message->client_id);
    }

    /**
     * Emit an email receipt event.
     */
    public function email(WebhookEvent $event, EmailMessage $message): int
    {
        return $this->emit($event, $this->payload->forEmail($event, $message), $message->client_id);
    }

    /**
     * Emit an SMPP submission event.
     */
    public function smpp(WebhookEvent $event, SmppMessage $message): int
    {
        return $this->emit($event, $this->payload->forSmpp($event, $message), $message->client_id);
    }

    /**
     * Queue the event again from an existing delivery row, body unchanged.
     */
    public function replay(WebhookDelivery $delivery): WebhookDelivery
    {
        $delivery->forceFill([
            'status' => WebhookDeliveryStatus::Pending,
            'response_status' => null,
            'response_body' => null,
            'error_message' => null,
            'next_attempt_at' => null,
            'delivered_at' => null,
            'failed_at' => null,
        ])->save();

        DeliverWebhook::dispatch($delivery->id)->onQueue((string) config('webhooks.queue', 'default'));

        return $delivery;
    }

    /**
     * Send a test event to one endpoint, synchronously.
     *
     * An operator who presses the button wants to know whether the URL works
     * now, so the answer is recorded on a delivery row before the screen reloads.
     */
    public function test(WebhookEndpoint $endpoint): WebhookDelivery
    {
        $event = $endpoint->events[0] ?? WebhookEvent::CampaignCreated->value;
        $event = WebhookEvent::tryFrom($event) ?? WebhookEvent::CampaignCreated;

        $delivery = WebhookDelivery::query()->create([
            'webhook_endpoint_id' => $endpoint->id,
            'client_id' => $endpoint->client_id,
            'event' => $event,
            'payload' => $this->payload->forTest($event),
            'status' => WebhookDeliveryStatus::Pending,
            'attempts' => 0,
        ]);

        $sent = $this->sender->send($delivery);

        if (! $sent && $delivery->refresh()->status !== WebhookDeliveryStatus::Failed) {
            $delivery->markFailed(
                $delivery->response_status,
                $delivery->response_body,
                $delivery->error_message ?? 'The test delivery failed.',
                true,
            );
        }

        Log::info('Webhook test event sent.', [
            'endpoint' => $endpoint->id,
            'delivered' => $sent,
        ]);

        return $delivery->refresh();
    }
}
