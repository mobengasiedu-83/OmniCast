<?php

use App\Enums\WebhookDeliveryStatus;
use App\Enums\WebhookEvent;
use App\Exceptions\WebhookDeliveryFailed;
use App\Jobs\DeliverWebhook;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\Webhooks\WebhookDispatcher;
use App\Services\Webhooks\WebhookSender;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    config()->set('webhooks.enabled', true);
    config()->set('webhooks.delivery.tries', 2);
    config()->set('webhooks.delivery.backoff', [30, 120]);
    config()->set('webhooks.delivery.disable_after_failures', 3);

    [$this->client, $this->wallet, $this->user] = clientWithWallet();
});

it('queues a delivery for every matching subscription', function (): void {
    Queue::fake();

    $subscribed = WebhookEndpoint::factory()->for($this->client)->create([
        'events' => [WebhookEvent::CampaignLaunched->value, WebhookEvent::SmsDelivered->value],
    ]);

    WebhookEndpoint::factory()->for($this->client)->create([
        'events' => [WebhookEvent::EmailBounced->value],
    ]);

    $queued = app(WebhookDispatcher::class)->emit(
        WebhookEvent::CampaignLaunched,
        [
            'id' => 'evt_test',
            'type' => 'campaign.launched',
            'version' => 'v1',
            'created_at' => now()->toIso8601String(),
            'data' => [],
        ],
        $this->client->id,
    );

    expect($queued)->toBe(1);

    $delivery = WebhookDelivery::query()->sole();

    expect($delivery->webhook_endpoint_id)->toBe($subscribed->id)
        ->and($delivery->event)->toBe(WebhookEvent::CampaignLaunched)
        ->and($delivery->status)->toBe(WebhookDeliveryStatus::Pending)
        ->and($delivery->payload['type'])->toBe('campaign.launched');

    Queue::assertPushed(DeliverWebhook::class);
});

it('writes nothing while outbound webhooks are switched off', function (): void {
    config()->set('webhooks.enabled', false);
    Queue::fake();

    WebhookEndpoint::factory()->for($this->client)->create([
        'events' => [WebhookEvent::CampaignLaunched->value],
    ]);

    $queued = app(WebhookDispatcher::class)->emit(WebhookEvent::CampaignLaunched, [], $this->client->id);

    expect($queued)->toBe(0)
        ->and(WebhookDelivery::query()->count())->toBe(0);

    Queue::assertNothingPushed();
});

it('signs the request and marks the delivery delivered on a 2xx', function (): void {
    Http::fake(['https://hooks.example.test/*' => Http::response('ok', 200)]);

    $endpoint = WebhookEndpoint::factory()->for($this->client)->create([
        'events' => [WebhookEvent::CampaignCompleted->value],
        'url' => 'https://hooks.example.test/omnicast',
    ]);

    $delivery = WebhookDelivery::factory()->for($endpoint, 'endpoint')->create([
        'client_id' => $this->client->id,
        'event' => WebhookEvent::CampaignCompleted,
    ]);

    expect(app(WebhookSender::class)->send($delivery))->toBeTrue();

    $delivery->refresh();

    expect($delivery->status)->toBe(WebhookDeliveryStatus::Delivered)
        ->and($delivery->response_status)->toBe(200)
        ->and($delivery->attempts)->toBe(1)
        ->and($delivery->delivered_at)->not->toBeNull();

    Http::assertSent(function ($request) use ($endpoint): bool {
        $signature = $request->header('X-Omnicast-Signature')[0] ?? '';
        parse_str(str_replace(',', '&', $signature), $parts);

        $expected = hash_hmac('sha256', ($parts['t'] ?? '').'.'.$request->body(), (string) $endpoint->secret);

        return $request->url() === 'https://hooks.example.test/omnicast'
            && ($parts['v1'] ?? '') === $expected
            && $request->header('X-Omnicast-Event')[0] === WebhookEvent::CampaignCompleted->value
            && $request->header('X-Omnicast-Api-Version')[0] === 'v1';
    });
});

it('leaves a failed delivery pending with the moment the retry is due', function (): void {
    Http::fake(['https://hooks.example.test/*' => Http::response('nope', 500)]);

    $endpoint = WebhookEndpoint::factory()->for($this->client)->create([
        'events' => [WebhookEvent::CampaignCompleted->value],
        'url' => 'https://hooks.example.test/omnicast',
    ]);

    $delivery = WebhookDelivery::factory()->for($endpoint, 'endpoint')->create([
        'client_id' => $this->client->id,
        'event' => WebhookEvent::CampaignCompleted,
    ]);

    expect(app(WebhookSender::class)->send($delivery))->toBeFalse();

    $delivery->refresh();

    expect($delivery->status)->toBe(WebhookDeliveryStatus::Pending)
        ->and($delivery->response_status)->toBe(500)
        ->and($delivery->error_message)->toContain('500')
        ->and($delivery->next_attempt_at)->not->toBeNull();
});

it('fails the delivery finally once the tries are spent and counts the endpoint failure', function (): void {
    Http::fake(['https://hooks.example.test/*' => Http::response('nope', 503)]);

    $endpoint = WebhookEndpoint::factory()->for($this->client)->create([
        'events' => [WebhookEvent::SmsFailed->value],
        'url' => 'https://hooks.example.test/omnicast',
    ]);

    $delivery = WebhookDelivery::factory()->for($endpoint, 'endpoint')->create([
        'client_id' => $this->client->id,
        'event' => WebhookEvent::SmsFailed,
    ]);

    $job = new DeliverWebhook($delivery->id);

    expect(fn () => $job->handle(app(WebhookSender::class)))
        ->toThrow(WebhookDeliveryFailed::class);

    $job->failed(new WebhookDeliveryFailed('The subscriber did not accept the delivery.'));

    $delivery->refresh();
    $endpoint->refresh();

    expect($delivery->status)->toBe(WebhookDeliveryStatus::Failed)
        ->and($delivery->failed_at)->not->toBeNull()
        ->and($endpoint->consecutive_failures)->toBe(1)
        ->and($endpoint->is_active)->toBeTrue();
});

it('switches an endpoint off after the failure threshold', function (): void {
    config()->set('webhooks.delivery.disable_after_failures', 2);

    $endpoint = WebhookEndpoint::factory()->for($this->client)->create([
        'events' => [WebhookEvent::SmsFailed->value],
        'consecutive_failures' => 1,
    ]);

    $delivery = WebhookDelivery::factory()->for($endpoint, 'endpoint')->create([
        'client_id' => $this->client->id,
        'event' => WebhookEvent::SmsFailed,
        'status' => WebhookDeliveryStatus::Delivering,
    ]);

    app(WebhookSender::class)->markFinalFailure($delivery);

    $endpoint->refresh();

    expect($endpoint->consecutive_failures)->toBe(2)
        ->and($endpoint->is_active)->toBeFalse()
        ->and($endpoint->disabled_at)->not->toBeNull();
});

it('sends the test event synchronously so the operator sees the answer', function (): void {
    Http::fake(['https://hooks.example.test/*' => Http::response('ok', 204)]);

    $endpoint = WebhookEndpoint::factory()->for($this->client)->create([
        'events' => [WebhookEvent::CampaignCreated->value],
        'url' => 'https://hooks.example.test/omnicast',
    ]);

    $delivery = app(WebhookDispatcher::class)->test($endpoint);

    expect($delivery->status)->toBe(WebhookDeliveryStatus::Delivered)
        ->and($delivery->event)->toBe(WebhookEvent::CampaignCreated);
});

it('refuses to deliver to a paused subscription', function (): void {
    Http::fake();

    $endpoint = WebhookEndpoint::factory()->for($this->client)->create([
        'events' => [WebhookEvent::CampaignCreated->value],
        'is_active' => false,
    ]);

    $delivery = WebhookDelivery::factory()->for($endpoint, 'endpoint')->create([
        'client_id' => $this->client->id,
        'event' => WebhookEvent::CampaignCreated,
    ]);

    expect(app(WebhookSender::class)->send($delivery))->toBeFalse()
        ->and($delivery->refresh()->status)->toBe(WebhookDeliveryStatus::Failed);

    Http::assertNothingSent();
});
