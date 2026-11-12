<?php

use App\Enums\PrivacyRetentionCategory;
use App\Enums\Role;
use App\Models\Client;
use App\Models\PrivacyRetentionPolicy;
use App\Models\User;
use App\Models\WhatsAppProvider;
use App\Models\WhatsAppProviderEvent;
use Inertia\Testing\AssertableInertia as Assert;

it('shows platform WhatsApp operations and release gates to an authorized operator', function (): void {
    $operator = User::factory()->create();
    $operator->syncRoles([Role::SuperAdmin->value]);
    config(['whatsapp.sending_enabled' => false, 'whatsapp.events.contract_enabled' => false]);

    $this->actingAs($operator)->get(route('admin.whatsapp.operations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/whatsapp/operations')
            ->where('sendingEnabled', false)
            ->where('eventsEnabled', false)
            ->where('launchFlags.inboundMessaging', false)
            ->where('launchFlags.providerTemplateSubmission', false)
            ->has('providerHealth')
            ->has('recipientStatuses'));
});

it('denies client users access to platform WhatsApp operations', function (): void {
    $client = Client::factory()->create();
    $user = User::factory()->forClient($client)->create();
    $user->syncRoles([Role::ClientAdmin->value]);

    $this->actingAs($user)->get(route('admin.whatsapp.operations.index'))->assertForbidden();
});

it('redacts old WhatsApp payload copies only after retention approval', function (): void {
    $provider = WhatsAppProvider::factory()->create();
    $event = WhatsAppProviderEvent::query()->create([
        'provider_id' => $provider->id,
        'provider_event_id' => 'retention-event',
        'event_type' => 'delivery',
        'status' => 'delivered',
        'signature_verified' => true,
        'payload_redacted' => ['message_id' => 'provider-id'],
    ]);
    $event->forceFill(['created_at' => now()->subDays(45)])->save();

    $this->artisan('whatsapp:prune-sensitive-payloads')->assertExitCode(0);
    expect($event->fresh()->payload_redacted)->not->toBeNull();

    $approver = User::factory()->create();
    PrivacyRetentionPolicy::query()->create([
        'category' => PrivacyRetentionCategory::WhatsAppSensitivePayloads,
        'retention_days' => 30,
        'approved_by' => $approver->id,
        'approved_at' => now(),
    ]);

    $this->artisan('whatsapp:prune-sensitive-payloads')->assertExitCode(0);

    expect($event->fresh()->payload_redacted)->toBeNull()
        ->and($event->fresh()->provider_event_id)->toBe('retention-event')
        ->and($event->fresh()->status)->toBe('delivered');
});
