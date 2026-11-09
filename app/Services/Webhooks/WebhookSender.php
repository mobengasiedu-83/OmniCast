<?php

namespace App\Services\Webhooks;

use App\Enums\WebhookDeliveryStatus;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\Notifications\SystemNotifier;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Posts one delivery to its subscriber and records what came back.
 *
 * The transport is deliberately thin: a 2xx is success, everything else is a
 * failure the queue retries. What the subscriber answered is kept on the row so
 * an operator can see a 401 or a TLS error without reading the worker log.
 */
class WebhookSender
{
    public function __construct(protected SystemNotifier $notifications) {}

    /**
     * Attempt the delivery, returning whether the subscriber accepted it.
     */
    public function send(WebhookDelivery $delivery): bool
    {
        $endpoint = $delivery->endpoint;

        if ($endpoint === null || ! $endpoint->isDeliverable()) {
            $delivery->markFailed(null, null, 'The subscription is switched off.', true);

            return false;
        }

        $body = (string) json_encode($delivery->payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $timestamp = time();
        $delivery->increment('attempts');
        $delivery->refresh();
        $delivery->forceFill(['status' => WebhookDeliveryStatus::Delivering])->save();

        try {
            $response = Http::withHeaders($this->headers($endpoint, $delivery, $body, $timestamp))
                ->timeout(max((int) config('webhooks.delivery.timeout', 15), 1))
                ->connectTimeout(max((int) config('webhooks.delivery.connect_timeout', 5), 1))
                ->withBody($body, 'application/json')
                ->post($endpoint->url);
        } catch (ConnectionException $exception) {
            $this->recordFailure($delivery, $endpoint, null, null, $exception->getMessage());

            return false;
        } catch (Throwable $exception) {
            $this->recordFailure($delivery, $endpoint, null, null, $exception->getMessage());

            return false;
        }

        if ($response->successful()) {
            $delivery->markDelivered($response->status(), $response->body());
            $endpoint->forceFill([
                'consecutive_failures' => 0,
                'last_success_at' => now(),
            ])->save();

            return true;
        }

        $this->recordFailure(
            $delivery,
            $endpoint,
            $response->status(),
            $response->body(),
            'The subscriber answered '.$response->status().'.',
        );

        return false;
    }

    /**
     * Mark a final failure and switch an endpoint off once it has failed enough.
     */
    public function markFinalFailure(WebhookDelivery $delivery): void
    {
        $delivery->refresh();
        $endpoint = $delivery->endpoint;

        $delivery->markFailed(
            $delivery->response_status,
            $delivery->response_body,
            $delivery->error_message ?? 'The delivery could not be completed.',
            true,
        );

        if ($endpoint === null) {
            return;
        }

        $failures = $endpoint->consecutive_failures + 1;
        $threshold = max((int) config('webhooks.delivery.disable_after_failures', 10), 1);

        $attributes = [
            'consecutive_failures' => $failures,
            'last_failure_at' => now(),
        ];

        if ($failures >= $threshold) {
            $attributes['is_active'] = false;
            $attributes['disabled_at'] = now();
        }

        $endpoint->forceFill($attributes)->save();

        Log::warning('Webhook delivery failed permanently.', [
            'endpoint' => $endpoint->id,
            'event' => $delivery->event->value,
            'attempts' => $delivery->attempts,
            'disabled' => $failures >= $threshold,
        ]);

        if ($failures >= $threshold) {
            $this->notifications->webhookDisabled($endpoint->refresh());
        }
    }

    /**
     * The signed headers every callback carries.
     *
     * @return array<string, string>
     */
    public function headers(WebhookEndpoint $endpoint, WebhookDelivery $delivery, string $body, int $timestamp): array
    {
        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'User-Agent' => (string) config('webhooks.delivery.user_agent', 'OmniCast-Webhooks/1.0'),
            config('webhooks.signature.header', 'X-Omnicast-Signature') => $this->signature($endpoint, $body, $timestamp),
            'X-Omnicast-Event' => $delivery->event->value,
            'X-Omnicast-Delivery' => (string) $delivery->id,
            'X-Omnicast-Api-Version' => $delivery->event->version(),
            'X-Omnicast-Attempt' => (string) max($delivery->attempts, 1),
        ];
    }

    /**
     * `t=<unix>,v1=<hmac-sha256 of "<unix>.<body>">`.
     */
    public function signature(WebhookEndpoint $endpoint, string $body, ?int $timestamp = null): string
    {
        $timestamp ??= time();

        return 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$body, (string) $endpoint->secret);
    }

    /**
     * Store a failed attempt and when the queue will try again.
     *
     * The delivery is left open here even on the last attempt: the worker owns
     * the retry budget, and its `failed()` hook is what finalises the row and
     * counts the failure against the subscription.
     */
    protected function recordFailure(
        WebhookDelivery $delivery,
        WebhookEndpoint $endpoint,
        ?int $status,
        ?string $body,
        string $error,
    ): void {
        $backoff = array_values(array_map('intval', (array) config('webhooks.delivery.backoff', [30])));
        $wait = $backoff[min(max($delivery->attempts - 1, 0), max(count($backoff) - 1, 0))] ?? 30;

        $delivery->markFailed($status, $body, $error, false, now()->addSeconds($wait));

        Log::info('Webhook delivery attempt failed.', [
            'endpoint' => $endpoint->id,
            'event' => $delivery->event->value,
            'attempt' => $delivery->attempts,
            'status' => $status,
            'error' => $error,
        ]);
    }
}
