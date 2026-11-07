<?php

use App\Models\PlatformSetting;
use Inertia\Testing\AssertableInertia as Assert;

it('lists the configured plan codes alongside the default plan', function (): void {
    PlatformSetting::query()->create(['group' => 'plans', 'key' => 'starter.channels.sms.enabled', 'value' => '1']);
    PlatformSetting::query()->create(['group' => 'plans', 'key' => 'starter.channels.voice.enabled', 'value' => '1']);
    PlatformSetting::query()->create(['group' => 'plans', 'key' => 'growth.channels.email.enabled', 'value' => '1']);

    $this->actingAs(platformUser())
        ->get(route('admin.settings.plans'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/settings/plans')
            ->where('plans', ['default', 'growth', 'starter']));
});

it('still shows the plans screen when no plan has been configured', function (): void {
    $this->actingAs(platformUser())
        ->get(route('admin.settings.plans'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/settings/plans')
            ->where('plans', ['default']));
});

it('assigns a configured plan to a client', function (): void {
    PlatformSetting::query()->create(['group' => 'plans', 'key' => 'growth.channels.sms.enabled', 'value' => '1']);

    [$client] = clientWithWallet();

    $this->actingAs(platformUser())
        ->put(route('admin.settings.plans.assign', ['client' => $client]), ['plan_code' => 'growth'])
        ->assertRedirect()
        ->assertSessionHas('toast');

    expect($client->refresh()->settings['plan_code'])->toBe('growth');
});

it('turns down a plan code the platform has not configured', function (): void {
    [$client] = clientWithWallet();

    $this->actingAs(platformUser())
        ->put(route('admin.settings.plans.assign', ['client' => $client]), ['plan_code' => 'ghost'])
        ->assertSessionHasErrors('plan_code');

    expect($client->refresh()->settings['plan_code'] ?? null)->toBeNull();
});
