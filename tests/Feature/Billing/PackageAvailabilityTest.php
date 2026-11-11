<?php

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PlatformSetting;
use App\Services\Billing\PackageCatalogue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    config()->set('billing.driver', 'log');
    config()->set('billing.rates', [
        'sms' => 0.04,
        'voice' => 0.30,
        'email' => 0.05,
    ]);

    [$this->client, $this->wallet, $this->user] = clientWithWallet(balance: 10.0);
});

it('ships a ten-pesewa sample package carrying the starter allocation', function (): void {
    $catalogue = app(PackageCatalogue::class);

    $sample = $catalogue->find('sample');
    $starter = $catalogue->find('starter');

    expect($sample)->not->toBeNull()
        ->and($sample['name'])->toBe('Sample')
        ->and($sample['price'])->toBe(0.1)
        ->and(array_column($sample['units'], 'service'))->toBe(['sms', 'voice', 'email'])
        ->and(array_column($sample['units'], 'quantity'))->toBe(array_column($starter['units'], 'quantity'))
        ->and($catalogue->isEnabled('sample'))->toBeFalse();
});

it('keeps a hidden package off the price list, the wallet and the checkout', function (): void {
    expect(app(PackageCatalogue::class)->keys())->not->toContain('sample');

    $this->get(route('pricing'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('packages', 4));

    $this->actingAs($this->user)
        ->get(route('wallet.index'))
        ->assertInertia(fn (Assert $page) => $page->has('packages', 4));

    $this->actingAs($this->user)
        ->post(route('wallet.checkout.store'), ['package' => 'sample'])
        ->assertSessionHasErrors('package');

    expect(Payment::query()->count())->toBe(0);
});

it('puts the sample on sale from the admin screen and sells it for ten pesewas', function (): void {
    $this->actingAs(platformUser())
        ->put(route('admin.packages.update', ['package' => 'sample']), ['enabled' => true])
        ->assertRedirect()
        ->assertSessionHas('toast');

    expect(app(PackageCatalogue::class)->keys())->toContain('sample');

    $this->get(route('pricing'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('packages', 5)
            ->where('packages.4.key', 'sample')
            ->where('packages.4.price', 0.1));

    $this->actingAs($this->user)
        ->post(route('wallet.checkout.store'), ['package' => 'sample'])
        ->assertRedirect();

    $payment = Payment::query()->sole();

    expect($payment->status)->toBe(PaymentStatus::Pending)
        ->and($payment->package_key)->toBe('sample')
        ->and((float) $payment->amount)->toBe(0.1)
        ->and($payment->description)->toContain('Sample package');
});

it('hides a package again when the platform turns it off', function (): void {
    $admin = platformUser();

    $this->actingAs($admin)
        ->put(route('admin.packages.update', ['package' => 'sample']), ['enabled' => true])
        ->assertRedirect();

    expect(app(PackageCatalogue::class)->keys())->toContain('sample');

    $this->actingAs($admin)
        ->put(route('admin.packages.update', ['package' => 'sample']), ['enabled' => false])
        ->assertRedirect();

    $setting = PlatformSetting::query()
        ->where('group', 'billing')
        ->where('key', 'sample.enabled')
        ->sole();

    expect($setting->value)->toBe('0')
        ->and($setting->updated_by)->toBe($admin->id)
        ->and(app(PackageCatalogue::class)->keys())->not->toContain('sample');
});

it('edits package details without freezing a rate-based allocation', function (): void {
    $this->actingAs(platformUser())
        ->patch(route('admin.packages.details.update', ['package' => 'starter']), [
            'name' => 'Launch Plus',
            'price' => '75.00',
            'popular' => true,
            'tagline' => 'A refreshed package description.',
        ])
        ->assertRedirect()
        ->assertSessionHas('toast');

    $package = app(PackageCatalogue::class)->find('starter');

    expect($package['name'])->toBe('Launch Plus')
        ->and($package['price'])->toBe(75.0)
        ->and($package['popular'])->toBeTrue()
        ->and($package['tagline'])->toBe('A refreshed package description.')
        ->and(array_column($package['units'], 'quantity'))->toBe([1875, 250, 1500]);

    $this->assertDatabaseMissing('platform_settings', [
        'group' => 'billing',
        'key' => 'starter.units.sms',
    ]);

    $this->get(route('pricing'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('packages.0.name', 'Launch Plus')
            ->where('packages.0.price', 75));

    $this->actingAs($this->user)
        ->post(route('wallet.checkout.store'), ['package' => 'starter'])
        ->assertRedirect();

    $payment = Payment::query()->sole();

    expect((float) $payment->amount)->toBe(75.0)
        ->and($payment->description)->toContain('Launch Plus package');
});

it('keeps an explicitly configured allocation when package details change', function (): void {
    $catalogue = app(PackageCatalogue::class);
    $originalUnits = $catalogue->find('sample')['units'];

    $this->actingAs(platformUser())
        ->patch(route('admin.packages.details.update', ['package' => 'sample']), [
            'name' => 'Sample Plus',
            'price' => '0.25',
            'popular' => false,
            'tagline' => 'A renamed sample package.',
        ])
        ->assertRedirect();

    expect($catalogue->find('sample')['units'])->toBe($originalUnits);

    $this->assertDatabaseMissing('platform_settings', [
        'group' => 'billing',
        'key' => 'sample.units.sms',
    ]);
});

it('saves custom package allocations when an administrator edits them', function (): void {
    $admin = platformUser();

    $this->actingAs($admin)
        ->patch(route('admin.packages.details.update', ['package' => 'starter']), [
            'name' => 'Launch',
            'price' => '51.25',
            'popular' => false,
            'tagline' => 'Start broadcasting with a small audience.',
            'units' => [
                'sms' => 900,
                'voice' => 0,
                'email' => 1200,
            ],
        ])
        ->assertRedirect();

    $package = app(PackageCatalogue::class)->find('starter');
    $smsSetting = PlatformSetting::query()
        ->where('group', 'billing')
        ->where('key', 'starter.units.sms')
        ->sole();

    expect(array_column($package['units'], 'service'))->toBe(['sms', 'email'])
        ->and(array_column($package['units'], 'quantity'))->toBe([900, 1200])
        ->and($smsSetting->value)->toBe('900')
        ->and($smsSetting->updated_by)->toBe($admin->id);
});

it('rejects package prices above the current top-up maximum', function (): void {
    config()->set('billing.limits.max_top_up', 80);

    $this->actingAs(platformUser())
        ->patch(route('admin.packages.details.update', ['package' => 'starter']), [
            'name' => 'Launch',
            'price' => '80.01',
            'popular' => false,
            'tagline' => 'Start broadcasting with a small audience.',
        ])
        ->assertSessionHasErrors('price');

    $this->assertDatabaseMissing('platform_settings', [
        'group' => 'billing',
        'key' => 'starter.name',
    ]);
});

it('rejects invalid custom allocations without saving package details', function (): void {
    $this->actingAs(platformUser())
        ->patch(route('admin.packages.details.update', ['package' => 'starter']), [
            'name' => 'Launch',
            'price' => '51.25',
            'popular' => false,
            'tagline' => 'Start broadcasting with a small audience.',
            'units' => [
                'sms' => -1,
                'voice' => 0,
                'email' => 1200,
                'unexpected' => 1,
            ],
        ])
        ->assertSessionHasErrors(['units.sms', 'units']);

    $this->assertDatabaseMissing('platform_settings', [
        'group' => 'billing',
        'key' => 'starter.name',
    ]);
});

it('lists every package with its availability on the admin screen', function (): void {
    $this->actingAs(platformUser())
        ->get(route('admin.packages.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/packages/index')
            ->has('packages', 5)
            ->where('packages.0.key', 'starter')
            ->where('packages.0.enabled', true)
            ->where('packages.4.key', 'sample')
            ->where('packages.4.enabled', false)
            ->where('currency', 'GHS')
            ->where('limits.min', 1));
});

it('keeps tenant users out of the package screen', function (): void {
    $this->actingAs($this->user)
        ->get(route('admin.packages.index'))
        ->assertForbidden();

    $this->actingAs($this->user)
        ->put(route('admin.packages.update', ['package' => 'sample']), ['enabled' => true])
        ->assertForbidden();

    $this->actingAs($this->user)
        ->patch(route('admin.packages.details.update', ['package' => 'sample']), [
            'name' => 'Sample',
            'price' => '0.10',
            'popular' => false,
            'tagline' => 'Example package.',
        ])
        ->assertForbidden();
});

it('rejects a package key the catalogue does not configure', function (): void {
    $this->actingAs(platformUser())
        ->put(route('admin.packages.update', ['package' => 'gold-plated']), ['enabled' => true])
        ->assertNotFound();

    $this->actingAs(platformUser())
        ->patch(route('admin.packages.details.update', ['package' => 'gold-plated']), [
            'name' => 'Gold Plated',
            'price' => '10.00',
            'popular' => false,
            'tagline' => 'Unknown package.',
        ])
        ->assertNotFound();
});
