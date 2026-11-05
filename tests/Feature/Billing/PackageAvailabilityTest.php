<?php

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PlatformSetting;
use App\Services\Billing\PackageCatalogue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    config()->set('billing.driver', 'log');

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
});

it('rejects a package key the catalogue does not configure', function (): void {
    $this->actingAs(platformUser())
        ->put(route('admin.packages.update', ['package' => 'gold-plated']), ['enabled' => true])
        ->assertNotFound();
});
