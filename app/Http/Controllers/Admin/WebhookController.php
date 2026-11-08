<?php

namespace App\Http\Controllers\Admin;

use App\Enums\WebhookEvent;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Settings\WebhookEndpointController as TenantWebhookController;
use App\Http\Requests\Webhook\WebhookEndpointRequest;
use App\Models\Client;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\Webhooks\WebhookDispatcher;
use App\Services\Webhooks\WebhookSubscriptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The platform's view of every webhook subscription and delivery.
 *
 * Support staff need to see a tenant's failing callback without borrowing the
 * tenant's login, so this screen lists every endpoint, lets a platform
 * subscription be created with a null client, and can replay a delivery whose
 * body was correct but whose subscriber was down.
 */
class WebhookController extends Controller
{
    public function __construct(
        protected TenantWebhookController $presenter,
        protected WebhookSubscriptions $subscriptions,
        protected WebhookDispatcher $dispatcher,
    ) {}

    /**
     * List every subscription and the most recent deliveries.
     */
    public function index(Request $request): Response
    {
        $clientId = $request->integer('client_id') ?: null;

        $endpoints = WebhookEndpoint::query()
            ->with('client:id,name')
            ->when($clientId !== null, fn ($query) => $query->where('client_id', $clientId))
            ->orderBy('client_id')
            ->orderBy('name')
            ->get();

        return Inertia::render('admin/webhooks/index', [
            'endpoints' => $endpoints
                ->map(fn (WebhookEndpoint $endpoint): array => [
                    ...$this->presenter->payload($endpoint),
                    'client' => $endpoint->client === null
                        ? null
                        : ['id' => $endpoint->client->id, 'name' => $endpoint->client->name],
                ])
                ->values()
                ->all(),
            'deliveries' => WebhookDelivery::query()
                ->with('endpoint:id,name,url')
                ->when($clientId !== null, fn ($query) => $query->where('client_id', $clientId))
                ->latest('id')
                ->limit(50)
                ->get()
                ->map(fn (WebhookDelivery $delivery): array => $this->presenter->deliveryPayload($delivery))
                ->values()
                ->all(),
            'events' => WebhookEvent::catalogue(),
            'clients' => Client::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Client $client): array => ['id' => $client->id, 'name' => $client->name])
                ->values()
                ->all(),
            'filters' => ['client_id' => $clientId],
            'enabled' => (bool) config('webhooks.enabled', true),
            'newSecret' => $request->session()->get('new_webhook_secret'),
        ]);
    }

    /**
     * Create a subscription for a tenant, or a platform-wide one.
     */
    public function store(WebhookEndpointRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $endpoint = $this->subscriptions->create(
            [
                'name' => (string) $request->string('name'),
                'url' => (string) $request->string('url'),
                'description' => $request->input('description'),
                'events' => $request->events(),
                'is_active' => $request->boolean('is_active', true),
            ],
            $request->filled('client_id') ? (int) $request->integer('client_id') : null,
            $user,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => "{$endpoint->name} was subscribed.",
        ]);
    }

    /**
     * Edit a subscription.
     */
    public function update(WebhookEndpointRequest $request, WebhookEndpoint $endpoint): RedirectResponse
    {
        $endpoint = $this->subscriptions->update($endpoint, [
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
    public function toggle(WebhookEndpoint $endpoint): RedirectResponse
    {
        $endpoint = $this->subscriptions->setActive($endpoint, ! $endpoint->is_active);

        return back()->with('toast', [
            'type' => 'success',
            'message' => $endpoint->is_active
                ? "{$endpoint->name} is receiving events again."
                : "{$endpoint->name} was switched off.",
        ]);
    }

    /**
     * Issue a new signing secret.
     */
    public function rotate(WebhookEndpoint $endpoint): RedirectResponse
    {
        $endpoint = $this->subscriptions->rotateSecret($endpoint);

        return back()
            ->with('new_webhook_secret', [
                'id' => $endpoint->id,
                'secret' => (string) $endpoint->secret,
            ])
            ->with('toast', [
                'type' => 'success',
                'message' => 'A new signing secret was issued. The subscriber has to be updated.',
            ]);
    }

    /**
     * Deliver a test event synchronously.
     */
    public function test(WebhookEndpoint $endpoint): RedirectResponse
    {
        $delivery = $this->subscriptions->test($endpoint);

        return back()->with('toast', [
            'type' => $delivery->delivered_at !== null ? 'success' : 'error',
            'message' => $delivery->delivered_at !== null
                ? 'The test event was delivered.'
                : 'The test event failed: '.($delivery->error_message ?? 'no 2xx answer.'),
        ]);
    }

    /**
     * Queue a delivery again with its original body.
     */
    public function replay(WebhookDelivery $delivery): RedirectResponse
    {
        $this->dispatcher->replay($delivery);

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Delivery #{$delivery->id} was queued again.",
        ]);
    }

    /**
     * Delete a subscription and its log.
     */
    public function destroy(WebhookEndpoint $endpoint): RedirectResponse
    {
        $name = $endpoint->name;
        $this->subscriptions->delete($endpoint);

        return back()->with('toast', [
            'type' => 'success',
            'message' => "{$name} was removed.",
        ]);
    }
}
