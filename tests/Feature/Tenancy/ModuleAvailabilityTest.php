<?php

use App\Enums\ClientService;
use App\Models\ClientEntitlement;
use App\Models\PlatformSetting;
use App\Models\SenderId;
use App\Services\ModuleAvailability;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

it('lists every module with its platform-wide reach', function (): void {
    [$client] = clientWithWallet();

    ClientEntitlement::factory()->for($client)->disabled()->create([
        'service' => ClientService::Voice,
    ]);

    $this->actingAs(platformUser())
        ->get(route('admin.settings.modules'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/settings/modules')
            ->has('modules', 3)
            ->where('modules.0.value', 'sms')
            ->where('modules.0.enabled', true)
            ->where('modules.0.clients_disabled', 0)
            ->where('modules.1.value', 'voice')
            ->where('modules.1.clients_disabled', 1)
            ->where('modules.2.value', 'email'));
});

it('switches a module off for every client and records who did it', function (): void {
    $admin = platformUser();

    $this->actingAs($admin)
        ->put(route('admin.settings.modules.update'), [
            'modules' => [
                ['value' => ClientService::Sms->value, 'enabled' => false],
                ['value' => ClientService::Voice->value, 'enabled' => true],
                ['value' => ClientService::Email->value, 'enabled' => true],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('toast');

    $setting = PlatformSetting::query()
        ->where('group', 'channels')
        ->where('key', 'sms.enabled')
        ->sole();

    $modules = app(ModuleAvailability::class);

    expect($setting->value)->toBe('0')
        ->and($setting->updated_by)->toBe($admin->id)
        ->and($modules->universallyEnabled(ClientService::Sms))->toBeFalse()
        ->and($modules->universallyEnabled(ClientService::Voice))->toBeTrue()
        ->and(Activity::query()->where('description', 'updated module availability')->exists())->toBeTrue();
});

it('closes a switched-off module to its tenants even when they are entitled', function (): void {
    [, , $user] = clientWithWallet();

    PlatformSetting::query()->create([
        'group' => 'channels',
        'key' => ClientService::Email->value.'.enabled',
        'value' => '0',
    ]);

    $this->actingAs($user)
        ->get(route('reports.email'))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('reports.sms'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('campaigns.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('modules.email', false)
            ->where('modules.sms', true)
            ->where('channels', fn ($channels): bool => collect($channels)->pluck('value')->doesntContain('email')));
});

it('keeps unrelated dashboard routes available when a module is switched off platform-wide', function (): void {
    [, , $user] = clientWithWallet();

    PlatformSetting::query()->create([
        'group' => 'channels',
        'key' => ClientService::Email->value.'.enabled',
        'value' => '0',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('modules.email', false)
            ->has('channelBreakdown', 3));
});

it('hides switched-off channels from the platform dashboard without blanking it', function (): void {
    PlatformSetting::query()->create([
        'group' => 'channels',
        'key' => ClientService::Email->value.'.enabled',
        'value' => '0',
    ]);

    $this->actingAs(platformUser())
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/dashboard')
            ->where('modules.email', false)
            ->where('channelBreakdown', [
                ['channel' => 'voice', 'label' => 'Voice', 'total' => 0],
                ['channel' => 'sms', 'label' => 'SMS', 'total' => 0],
                ['channel' => 'multi_channel', 'label' => 'Multi-Channel', 'total' => 0],
            ]));
});

it('drops a module the tenant switched off from its own screens', function (): void {
    [$client, , $user] = clientWithWallet();

    ClientEntitlement::factory()->for($client)->disabled()->create([
        'service' => ClientService::Voice,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('modules.voice', false)
            ->where('modules.sms', true));

    $this->actingAs($user)
        ->get(route('reports.voice'))
        ->assertForbidden();
});

it('hides sender identities that belong to a switched-off module', function (): void {
    [$client, , $user] = clientWithWallet();

    $sms = SenderId::factory()->for($client)->sms()->approved()->create();
    SenderId::factory()->for($client)->email()->approved()->create();

    ClientEntitlement::factory()->for($client)->disabled()->create([
        'service' => ClientService::Email,
    ]);

    $this->actingAs($user)
        ->get(route('sender-ids.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('senderIds.data', 1)
            ->where('senderIds.data.0.id', $sms->id)
            ->where('channels', fn ($channels): bool => collect($channels)->pluck('value')->doesntContain('email')));
});

it('keeps tenant users out of the module screen', function (): void {
    [, , $user] = clientWithWallet();

    $this->actingAs($user)
        ->get(route('admin.settings.modules'))
        ->assertForbidden();

    $this->actingAs($user)
        ->put(route('admin.settings.modules.update'), [
            'modules' => [
                ['value' => ClientService::Sms->value, 'enabled' => false],
            ],
        ])
        ->assertForbidden();

    expect(PlatformSetting::query()->where('group', 'channels')->count())->toBe(0);
});

it('rejects a module the platform does not deliver', function (): void {
    $this->actingAs(platformUser())
        ->put(route('admin.settings.modules.update'), [
            'modules' => [
                ['value' => 'fax', 'enabled' => true],
            ],
        ])
        ->assertSessionHasErrors('modules.0.value');

    expect(PlatformSetting::query()->where('group', 'channels')->count())->toBe(0);
});
