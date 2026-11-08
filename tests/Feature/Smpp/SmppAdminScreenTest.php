<?php

use App\Enums\SmppBindMode;
use App\Enums\WebhookDeliveryStatus;
use App\Jobs\DeliverWebhook;
use App\Models\SmppAccount;
use App\Models\SmppMessage;
use App\Models\SmppSession;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();

    [$this->client, $this->wallet, $this->clientUser] = clientWithWallet();
    $this->admin = platformUser();
});

it('renders the SMPP operations screen', function (): void {
    $account = SmppAccount::factory()->for($this->client)->create(['system_id' => 'ESMEONE']);

    SmppSession::factory()->create([
        'smpp_account_id' => $account->id,
        'system_id' => 'ESMEONE',
    ]);

    SmppMessage::factory()->for($this->client)->create([
        'smpp_account_id' => $account->id,
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.smpp.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/smpp/index')
            ->has('accounts', 1)
            ->where('accounts.0.system_id', 'ESMEONE')
            ->has('sessions', 1)
            ->has('messages', 1)
            ->has('summary')
            ->has('bindModes'));
});

it('issues an SMPP account and shows the password once', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.smpp.store'), [
            'client_id' => $this->client->id,
            'name' => 'Telco integration',
            'system_id' => 'ESMENEW',
            'bind_mode' => SmppBindMode::Transceiver->value,
            'allowed_sources' => 'omnicast, 233244000001',
            'throughput_per_second' => 25,
        ])
        ->assertRedirect()
        ->assertSessionHas('new_smpp_password');

    $account = SmppAccount::query()->sole();

    expect($account->system_id)->toBe('ESMENEW')
        ->and($account->client_id)->toBe($this->client->id)
        ->and($account->bind_mode)->toBe(SmppBindMode::Transceiver)
        ->and($account->allowed_sources)->toBe(['OMNICAST', '233244000001'])
        ->and($account->throughput_per_second)->toBe(25)
        ->and($account->is_active)->toBeTrue()
        ->and($account->password_hash)->not->toBe('')
        ->and(str_starts_with($account->password_hash, '$2y$'))->toBeTrue();
});

it('rotates the bind password and can switch the account off', function (): void {
    $account = SmppAccount::factory()->for($this->client)->create();
    $before = $account->password_hash;

    $this->actingAs($this->admin)
        ->patch(route('admin.smpp.rotate', ['account' => $account->id]))
        ->assertRedirect()
        ->assertSessionHas('new_smpp_password');

    $account->refresh();

    expect($account->password_hash)->not->toBe($before)
        ->and(Hash::check('smpp-pass', $account->password_hash))->toBeFalse();

    $this->actingAs($this->admin)
        ->patch(route('admin.smpp.toggle', ['account' => $account->id]))
        ->assertRedirect();

    expect($account->refresh()->is_active)->toBeFalse();
});

it('refuses the SMPP screen to a tenant user', function (): void {
    $this->actingAs($this->clientUser)
        ->get(route('admin.smpp.index'))
        ->assertForbidden();
});

it('renders the platform webhook screen and replays a delivery', function (): void {
    Queue::fake();

    $endpoint = WebhookEndpoint::factory()->for($this->client)->create(['name' => 'Tenant hook']);
    $delivery = WebhookDelivery::factory()->for($endpoint, 'endpoint')->create([
        'client_id' => $this->client->id,
        'status' => WebhookDeliveryStatus::Failed,
        'attempts' => 3,
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.webhooks.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/webhooks/index')
            ->has('endpoints', 1)
            ->where('endpoints.0.name', 'Tenant hook')
            ->has('deliveries', 1)
            ->has('clients'));

    $this->actingAs($this->admin)
        ->post(route('admin.webhook-deliveries.replay', ['delivery' => $delivery->id]))
        ->assertRedirect();

    expect($delivery->refresh()->status)->toBe(WebhookDeliveryStatus::Pending);

    Queue::assertPushed(DeliverWebhook::class);
});
