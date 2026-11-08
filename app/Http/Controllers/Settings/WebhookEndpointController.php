<?php

namespace App\Http\Controllers\Settings;

use App\Enums\WebhookEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Webhook\WebhookEndpointRequest;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\Webhooks\WebhookSubscriptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A tenant's outbound webhook subscriptions.
 *
 * The callbacks are how an integration learns that a campaign launched or a
 * message was delivered without polling the API, so the screen shows the
 * signing secret once, the events each URL receives and the last failures.
 */
class WebhookEndpointController extends Controller
{
    /**
     * List the tenant's subscriptions and their recent deliveries.
     */
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $clientId = (int) $user->client_id;

        return Inertia::render('settings/webhooks', [
            'endpoints' => WebhookEndpoint::query()
                ->forClient($clientId)
                ->orderBy('name')
                ->get()
                ->map(fn (WebhookEndpoint $endpoint): array => $this->payload($endpoint))
                ->values()
                ->all(),
            'deliveries' => WebhookDelivery::query()
                ->forClient($clientId)
                ->with('endpoint:id,name,url')
                ->latest('id')
                ->limit(25)
                ->get()
                ->map(fn (WebhookDelivery $delivery): array => $this->deliveryPayload($delivery))
                ->values()
                ->all(),
            'events' => WebhookEvent::catalogue(),
            'signature' => [
                'header' => (string) config('webhooks.signature.header', 'X-Omnicast-Signature'),
                'tolerance' => (int) config('webhooks.signature.tolerance', 300),
                'version' => (string) config('api.version', 'v1'),
            ],
            'enabled' => (bool) config('webhooks.enabled', true),
            'newSecret' => $request->session()->get('new_webhook_secret'),
        ]);
    }

    /**
     * Subscribe the tenant to more events.
     */
    public function store(WebhookEndpointRequest $request, WebhookSubscriptions $subscriptions): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $endpoint = $subscriptions->create(
            [
                'name' => (string) $request->string('name'),
                'url' => (string) $request->string('url'),
                'description' => $request->input('description'),
                'events' => $request->events(),
                'is_active' => $request->boolean('is_active', true),
            ],
            (int) $user->client_id,
            $user,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => "{$endpoint->name} will now receive the selected events.",
        ]);
    }

    /**
     * Edit a subscription.
     */
    public function update(
        WebhookEndpointRequest $request,
        WebhookEndpoint $endpoint,
        WebhookSubscriptions $subscriptions,
    ): RedirectResponse {
        $this->authorizeEndpoint($request, $endpoint);

        $endpoint = $subscriptions->update($endpoint, [
            'name' => (string) $request->string('name'),
            'url' => (string) $request->string('url'),
            'description' => $request->input('description'),
            'events' => $request->events(),
            'is_active' => $request->boolean('is_active', $endpoint->is_active),
        ]);

        return back()->with('toast', [
            'type' => 'success',
            'message' => "{$endpoint->name} was updated and its failure count reset.",
        ]);
    }

    /**
     * Switch a subscription on or off.
     */
    public function toggle(Request $request, WebhookEndpoint $endpoint, WebhookSubscriptions $subscriptions): RedirectResponse
    {
        $this->authorizeEndpoint($request, $endpoint);

        $endpoint = $subscriptions->setActive($endpoint, ! $endpoint->is_active);

        return back()->with('toast', [
            'type' => 'success',
            'message' => $endpoint->is_active
                ? "{$endpoint->name} is receiving events again."
                : "{$endpoint->name} will not receive events.",
        ]);
    }

    /**
     * Issue a new signing secret.
     */
    public function rotate(Request $request, WebhookEndpoint $endpoint, WebhookSubscriptions $subscriptions): RedirectResponse
    {
        $this->authorizeEndpoint($request, $endpoint);

        $endpoint = $subscriptions->rotateSecret($endpoint);

        return back()
            ->with('new_webhook_secret', [
                'id' => $endpoint->id,
                'secret' => (string) $endpoint->secret,
            ])
            ->with('toast', [
                'type' => 'success',
                'message' => 'A new signing secret was issued. Update the subscriber now.',
            ]);
    }

    /**
     * Send a test event so the operator learns whether the URL works.
     */
    public function test(Request $request, WebhookEndpoint $endpoint, WebhookSubscriptions $subscriptions): RedirectResponse
    {
        $this->authorizeEndpoint($request, $endpoint);

        $delivery = $subscriptions->test($endpoint);
        $delivered = $delivery->delivered_at !== null;

        return back()->with('toast', [
            'type' => $delivered ? 'success' : 'error',
            'message' => $delivered
                ? 'The test event was delivered.'
                : 'The test event failed: '.($delivery->error_message ?? 'the endpoint did not answer with 2xx.'),
        ]);
    }

    /**
     * Delete a subscription.
     */
    public function destroy(Request $request, WebhookEndpoint $endpoint, WebhookSubscriptions $subscriptions): RedirectResponse
    {
        $this->authorizeEndpoint($request, $endpoint);

        $name = $endpoint->name;
        $subscriptions->delete($endpoint);

        return back()->with('toast', [
            'type' => 'success',
            'message' => "{$name} was removed and will receive nothing further.",
        ]);
    }

    /**
     * Refuse a subscription that belongs to another tenant.
     */
    protected function authorizeEndpoint(Request $request, WebhookEndpoint $endpoint): void
    {
        abort_unless(
            (int) $endpoint->client_id === (int) $request->user()?->client_id,
            404,
        );
    }

    /**
     * The screen shape of one subscription.
     *
     * @return array<string, mixed>
     */
    public function payload(WebhookEndpoint $endpoint): array
    {
        return [
            'id' => $endpoint->id,
            'name' => $endpoint->name,
            'url' => $endpoint->url,
            'description' => $endpoint->description,
            'events' => $endpoint->events,
            'api_version' => $endpoint->api_version,
            'is_active' => $endpoint->is_active,
            'masked_secret' => $endpoint->maskedSecret(),
            'consecutive_failures' => $endpoint->consecutive_failures,
            'disabled_at' => $endpoint->disabled_at?->toIso8601String(),
            'last_success_at' => $endpoint->last_success_at?->toIso8601String(),
            'last_failure_at' => $endpoint->last_failure_at?->toIso8601String(),
        ];
    }

    /**
     * The screen shape of one delivery.
     *
     * @return array<string, mixed>
     */
    public function deliveryPayload(WebhookDelivery $delivery): array
    {
        return [
            'id' => $delivery->id,
            'event' => $delivery->event->value,
            'status' => $delivery->status->value,
            'tone' => $delivery->status->tone(),
            'attempts' => $delivery->attempts,
            'response_status' => $delivery->response_status,
            'error_message' => $delivery->error_message,
            'endpoint' => [
                'id' => $delivery->endpoint?->id,
                'name' => $delivery->endpoint?->name,
                'url' => $delivery->endpoint?->url,
            ],
            'created_at' => $delivery->created_at?->toIso8601String(),
            'delivered_at' => $delivery->delivered_at?->toIso8601String(),
        ];
    }
}
