<?php

use App\Enums\WebhookEvent;
use App\Models\WebhookEndpoint;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    config()->set('webhooks.enabled', true);
    $this->withoutVite();

    [$this->client, $this->wallet, $this->user] = clientWithWallet();
});

it('renders the tenant webhook screen with its own subscriptions', function (): void {
    WebhookEndpoint::factory()->for($this->client)->create([
        'name' => 'Billing hook',
        'events' => [WebhookEvent::SmsDelivered->value],
    ]);

    $other = clientWithWallet()[0];
    WebhookEndpoint::factory()->for($other)->create(['name' => 'Other hook']);

    $this->actingAs($this->user)
        ->get(route('webhooks.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/webhooks')
            ->has('endpoints', 1)
            ->where('endpoints.0.name', 'Billing hook')
            ->has('events')
            ->where('signature.version', 'v1'));
});

it('subscribes a new endpoint with a generated secret', function (): void {
    $this->actingAs($this->user)
        ->post(route('webhooks.store'), [
            'name' => 'CRM hook',
            'url' => 'https://crm.example.test/hooks/omnicast',
            'events' => [WebhookEvent::CampaignLaunched->value, WebhookEvent::SmsDelivered->value],
            'is_active' => true,
        ])
        ->assertRedirect();

    $endpoint = WebhookEndpoint::query()->sole();

    expect($endpoint->client_id)->toBe($this->client->id)
        ->and($endpoint->name)->toBe('CRM hook')
        ->and($endpoint->events)->toBe([WebhookEvent::CampaignLaunched->value, WebhookEvent::SmsDelivered->value])
        ->and($endpoint->secret)->toStartWith('whsec_')
        ->and($endpoint->is_active)->toBeTrue()
        ->and($endpoint->created_by)->toBe($this->user->id);
});

it('refuses a plain http endpoint', function (): void {
    $this->actingAs($this->user)
        ->post(route('webhooks.store'), [
            'name' => 'Plain hook',
            'url' => 'http://crm.example.test/hooks/omnicast',
            'events' => [WebhookEvent::CampaignLaunched->value],
        ])
        ->assertSessionHasErrors('url');

    expect(WebhookEndpoint::query()->count())->toBe(0);
});

it('will not touch a subscription that belongs to another tenant', function (): void {
    $other = clientWithWallet()[0];
    $endpoint = WebhookEndpoint::factory()->for($other)->create();

    $this->actingAs($this->user)
        ->patch(route('webhooks.toggle', ['endpoint' => $endpoint->id]))
        ->assertNotFound();

    $this->actingAs($this->user)
        ->delete(route('webhooks.destroy', ['endpoint' => $endpoint->id]))
        ->assertNotFound();

    expect($endpoint->refresh()->is_active)->toBeTrue();
});

it('issues a new signing secret on rotation', function (): void {
    $endpoint = WebhookEndpoint::factory()->for($this->client)->create();
    $before = $endpoint->secret;

    $this->actingAs($this->user)
        ->patch(route('webhooks.rotate', ['endpoint' => $endpoint->id]))
        ->assertRedirect()
        ->assertSessionHas('new_webhook_secret');

    expect($endpoint->refresh()->secret)->not->toBe($before)
        ->and($endpoint->secret)->toStartWith('whsec_');
});
