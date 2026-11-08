<?php

namespace App\Services\Webhooks;

use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Str;

/**
 * Creates, edits and repairs webhook subscriptions.
 *
 * Both the tenant screen and the platform screen write through here, so the
 * secret format, the reset of the failure counter and the audit fields are one
 * implementation rather than two that drift apart.
 */
class WebhookSubscriptions
{
    public function __construct(protected WebhookDispatcher $dispatcher) {}

    /**
     * Subscribe a new URL to the chosen events.
     *
     * @param  array{name: string, url: string, description?: string|null, events: list<string>, is_active?: bool}  $attributes
     */
    public function create(array $attributes, ?int $clientId, ?User $user): WebhookEndpoint
    {
        return WebhookEndpoint::query()->create([
            'client_id' => $clientId,
            'name' => $attributes['name'],
            'url' => $attributes['url'],
            'description' => $attributes['description'] ?? null,
            'events' => $attributes['events'],
            'secret' => $this->newSecret(),
            'api_version' => 'v1',
            'is_active' => $attributes['is_active'] ?? true,
            'consecutive_failures' => 0,
            'created_by' => $user?->id,
        ]);
    }

    /**
     * Apply an edit and give the subscription a fresh start.
     *
     * A URL that was fixed has to be allowed to receive events again, so saving
     * the subscription clears the failure counter and removes the disable.
     *
     * @param  array{name: string, url: string, description?: string|null, events: list<string>, is_active?: bool}  $attributes
     */
    public function update(WebhookEndpoint $endpoint, array $attributes): WebhookEndpoint
    {
        $endpoint->forceFill([
            'name' => $attributes['name'],
            'url' => $attributes['url'],
            'description' => $attributes['description'] ?? null,
            'events' => $attributes['events'],
            'is_active' => $attributes['is_active'] ?? $endpoint->is_active,
            'consecutive_failures' => 0,
            'disabled_at' => null,
        ])->save();

        return $endpoint->refresh();
    }

    /**
     * Switch a subscription on or off without touching its URL.
     */
    public function setActive(WebhookEndpoint $endpoint, bool $active): WebhookEndpoint
    {
        $endpoint->forceFill([
            'is_active' => $active,
            'consecutive_failures' => $active ? 0 : $endpoint->consecutive_failures,
            'disabled_at' => $active ? null : $endpoint->disabled_at,
        ])->save();

        return $endpoint->refresh();
    }

    /**
     * Issue a new signing secret, which invalidates the one the subscriber holds.
     */
    public function rotateSecret(WebhookEndpoint $endpoint): WebhookEndpoint
    {
        $endpoint->forceFill(['secret' => $this->newSecret()])->save();

        return $endpoint->refresh();
    }

    /**
     * Remove the subscription and the delivery log it owns.
     */
    public function delete(WebhookEndpoint $endpoint): void
    {
        $endpoint->deliveries()->delete();
        $endpoint->delete();
    }

    /**
     * Send a test event and record the answer on the endpoint's log.
     */
    public function test(WebhookEndpoint $endpoint): WebhookDelivery
    {
        return $this->dispatcher->test($endpoint);
    }

    /**
     * A fresh `whsec_` secret, long enough that guessing it is hopeless.
     */
    public function newSecret(): string
    {
        return (string) config('webhooks.signature.secret_prefix', 'whsec_').Str::random(40);
    }
}
