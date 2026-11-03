<?php

use App\Enums\Channel;
use App\Enums\ClientService;
use App\Models\Campaign;
use App\Models\ClientEntitlement;
use Spatie\Activitylog\Models\Activity;

it('updates the entitlement grid of a tenant', function (): void {
    [$client] = clientWithWallet();

    $this->actingAs(platformUser())
        ->put(route('admin.clients.entitlements.update', $client), [
            'entitlements' => [
                [
                    'service' => ClientService::Sms->value,
                    'is_enabled' => true,
                    'monthly_quota' => 50000,
                    'daily_quota' => 2000,
                    'notes' => 'Bundled with the starter plan.',
                ],
                [
                    'service' => ClientService::Voice->value,
                    'is_enabled' => false,
                ],
                [
                    'service' => ClientService::Email->value,
                    'is_enabled' => true,
                ],
            ],
        ])
        ->assertSessionHasNoErrors();

    $sms = ClientEntitlement::query()
        ->where('client_id', $client->id)
        ->where('service', ClientService::Sms->value)
        ->sole();

    expect($sms->is_enabled)->toBeTrue()
        ->and($sms->monthly_quota)->toBe(50000)
        ->and($sms->daily_quota)->toBe(2000)
        ->and($sms->quotaLabel())->toBe('50,000 / month · 2,000 / day')
        ->and($client->refresh()->allowsService(ClientService::Voice))->toBeFalse()
        ->and($client->allowsService(ClientService::Email))->toBeTrue()
        ->and(Activity::query()->where('description', 'updated client entitlements')->exists())->toBeTrue();
});

it('blocks a campaign on a channel the tenant is not entitled to', function (): void {
    [$client, , $user] = clientWithWallet();

    ClientEntitlement::factory()->for($client)->disabled()->create([
        'service' => ClientService::Sms,
    ]);

    $this->actingAs($user)
        ->post(route('campaigns.store'), [
            'name' => 'Blocked reminder',
            'channel' => 'sms',
            'sms_message' => 'Your order is ready for collection.',
            'schedule_type' => 'now',
        ])
        ->assertSessionHasErrors('channel');

    expect(Campaign::query()->count())->toBe(0);
});

it('allows a channel that has no configured entitlement', function (): void {
    [$client, , $user] = clientWithWallet();

    $this->actingAs($user)
        ->post(route('campaigns.store'), [
            'name' => 'Allowed reminder',
            'channel' => 'sms',
            'sms_message' => 'Your order is ready for collection.',
            'schedule_type' => 'now',
        ])
        ->assertSessionHasNoErrors();

    expect(Campaign::query()->count())->toBe(1)
        ->and($client->refresh()->allowsChannel(Channel::Sms))->toBeTrue();
});

it('only lets the platform team change entitlements', function (): void {
    [$client, , $user] = clientWithWallet();

    $this->actingAs($user)
        ->put(route('admin.clients.entitlements.update', $client), [
            'entitlements' => [
                ['service' => ClientService::Sms->value, 'is_enabled' => false],
            ],
        ])
        ->assertForbidden();

    expect(ClientEntitlement::query()->where('client_id', $client->id)->count())->toBe(0);
});
