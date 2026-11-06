<?php

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PlatformSetting;
use App\Services\Billing\CheckoutService;
use App\Services\Billing\CreditEstimator;
use App\Services\Billing\PricingSettings;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The price list the platform team submits on Admin → Dynamic pricing.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function dynamicPricingPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'dynamic_pricing' => true,
        'rates' => ['sms' => 0.04, 'voice' => 0.30, 'email' => 0.05],
        'limits' => ['min' => 1, 'max' => 100000],
        'slider' => ['min' => 10, 'max' => 5000, 'step' => 10, 'default' => 100],
    ], $overrides);
}

beforeEach(function (): void {
    config()->set('billing.driver', 'log');

    [$this->client, $this->wallet, $this->user] = clientWithWallet(balance: 10.0);
});

it('publishes the selling rates and the slider with the price list', function (): void {
    $this->get(route('pricing'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('dynamic_pricing', true)
            ->has('rates', 3)
            ->where('rates.0.service', 'sms')
            ->where('rates.0.unit_price', 0.04)
            ->where('rates.1.service', 'voice')
            ->where('rates.1.unit_price', 0.3)
            ->where('rates.2.service', 'email')
            ->where('rates.2.unit_price', 0.05)
            ->where('slider.min', 10)
            ->where('slider.max', 5000)
            ->where('slider.step', 10)
            ->where('slider.default', 100));
});

it('quotes an amount in sms parts, voice minutes and emails', function (): void {
    $estimate = app(CreditEstimator::class)->estimate(150.0);

    expect($estimate['amount'])->toBe(150.0)
        ->and($estimate['formatted'])->toBe('GHS 150.00')
        ->and(array_column($estimate['lines'], 'service'))->toBe(['sms', 'voice', 'email'])
        ->and(array_column($estimate['lines'], 'quantity'))->toBe([3750, 500, 3000])
        ->and($estimate['lines'][0]['formatted'])->toBe('3,750');
});

it('quotes each unit the way campaigns are billed for it', function (): void {
    $rates = collect(app(CreditEstimator::class)->rates())->keyBy('service');

    expect($rates['sms']['note'])->toBe('One unit is 160 characters to one number')
        ->and($rates['sms']['price_formatted'])->toBe('GHS 0.04')
        ->and($rates['voice']['note'])->toBe('One unit is a minute of the call to one number')
        ->and($rates['voice']['price_formatted'])->toBe('GHS 0.30');
});

it('opens a top up for the amount the client chose', function (): void {
    $response = $this->actingAs($this->user)
        ->post(route('wallet.checkout.store'), ['amount' => 150]);

    $payment = Payment::query()->sole();

    $response->assertRedirect(route('wallet.checkout.callback', ['reference' => $payment->reference]));

    expect($payment->status)->toBe(PaymentStatus::Pending)
        ->and($payment->package_key)->toBe(CheckoutService::CUSTOM_PACKAGE)
        ->and((float) $payment->amount)->toBe(150.0)
        ->and((float) $payment->amount_paid)->toBe(0.0)
        ->and($payment->description)->toBe('Custom top up of GHS 150.00 · 3,750 SMS, 500 Voice minutes, 3,000 Emails')
        ->and(data_get($payment->metadata, 'package'))->toBe('custom')
        ->and((float) $this->wallet->refresh()->balance)->toBe(10.0);
});

it('keeps a half-typed amount out of the checkout', function (): void {
    $this->actingAs($this->user)
        ->post(route('wallet.checkout.store'), ['amount' => 'abc'])
        ->assertSessionHasErrors('amount');

    $this->actingAs($this->user)
        ->post(route('wallet.checkout.store'), [])
        ->assertSessionHasErrors('amount');

    expect(Payment::query()->count())->toBe(0);
});

it('refuses an amount outside the top up limits', function (): void {
    $this->actingAs($this->user)
        ->post(route('wallet.checkout.store'), ['amount' => 0.5])
        ->assertSessionHasErrors('amount');

    $this->actingAs($this->user)
        ->post(route('wallet.checkout.store'), ['amount' => 100000.01])
        ->assertSessionHasErrors('amount');

    expect(Payment::query()->count())->toBe(0);
});

it('refuses an amount while dynamic pricing is switched off', function (): void {
    app(PricingSettings::class)->save(['dynamic_pricing' => false]);

    $this->get(route('pricing'))
        ->assertInertia(fn (Assert $page) => $page->where('dynamic_pricing', false));

    $this->actingAs($this->user)
        ->get(route('wallet.index'))
        ->assertInertia(fn (Assert $page) => $page->where('dynamic_pricing', false));

    $this->actingAs($this->user)
        ->post(route('wallet.checkout.store'), ['amount' => 150])
        ->assertSessionHasErrors('amount');

    expect(Payment::query()->count())->toBe(0);

    $this->actingAs($this->user)
        ->post(route('wallet.checkout.store'), ['package' => 'starter'])
        ->assertRedirect();

    expect(Payment::query()->sole()->package_key)->toBe('starter');
});

it('refuses a custom top up started in code while the slider is off', function (): void {
    app(PricingSettings::class)->save(['dynamic_pricing' => false]);

    expect(fn () => app(CheckoutService::class)->start(
        $this->client,
        $this->user,
        null,
        route('wallet.index'),
        150.0,
    ))->toThrow(ValidationException::class);

    expect(Payment::query()->count())->toBe(0);
});

it('shows the platform team the price list with its overrides', function (): void {
    $this->actingAs(platformUser())
        ->get(route('admin.pricing.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/pricing/index')
            ->where('currency', 'GHS')
            ->where('dynamic_pricing', true)
            ->has('rates', 3)
            ->where('rates.0.unit_price', 0.04)
            ->where('limits.min', 1)
            ->where('limits.max', 100000)
            ->where('slider.min', 10)
            ->where('slider.default', 100)
            ->where('defaults.rates', ['sms' => 0.04, 'voice' => 0.3, 'email' => 0.05])
            ->where('overrides', [])
            ->has('preview.lines', 3));
});

it('stores a new price list as billing settings that win over the config', function (): void {
    $admin = platformUser();

    $this->actingAs($admin)
        ->put(route('admin.pricing.update'), dynamicPricingPayload([
            'rates' => ['sms' => 0.05, 'voice' => 0.25, 'email' => 0.10],
            'limits' => ['min' => 5, 'max' => 2000],
            'slider' => ['min' => 20, 'max' => 1500, 'step' => 5, 'default' => 250],
        ]))
        ->assertRedirect()
        ->assertSessionHas('toast');

    $settings = app(PricingSettings::class);

    expect($settings->rate('sms'))->toBe(0.05)
        ->and($settings->rate('voice'))->toBe(0.25)
        ->and($settings->rate('email'))->toBe(0.1)
        ->and($settings->limits())->toBe(['min' => 5.0, 'max' => 2000.0])
        ->and($settings->slider()['default'])->toBe(250.0);

    $setting = PlatformSetting::query()
        ->where('group', 'billing')
        ->where('key', 'rates.sms')
        ->sole();

    expect($setting->value)->toBe('0.05')
        ->and($setting->updated_by)->toBe($admin->id);

    $this->get(route('pricing'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('rates.0.unit_price', 0.05)
            ->where('limits.min', 5)
            ->where('slider.default', 250));

    $this->actingAs($this->user)
        ->post(route('wallet.checkout.store'), ['amount' => 4])
        ->assertSessionHasErrors('amount');

    $this->actingAs($this->user)
        ->post(route('wallet.checkout.store'), ['amount' => 50])
        ->assertRedirect();

    $payment = Payment::query()->sole();

    expect($payment->package_key)->toBe(CheckoutService::CUSTOM_PACKAGE)
        ->and((float) $payment->amount)->toBe(50.0)
        ->and($payment->description)->toContain('1,000 SMS');
});

it('takes the slider off the wallet when the platform switches it off', function (): void {
    $this->actingAs(platformUser())
        ->put(route('admin.pricing.update'), dynamicPricingPayload(['dynamic_pricing' => false]))
        ->assertRedirect()
        ->assertSessionHas('toast');

    expect(app(PricingSettings::class)->dynamicEnabled())->toBeFalse();

    $this->actingAs($this->user)
        ->get(route('wallet.index'))
        ->assertInertia(fn (Assert $page) => $page->where('dynamic_pricing', false));

    $this->get(route('pricing'))
        ->assertInertia(fn (Assert $page) => $page->where('dynamic_pricing', false));
});

it('rejects a price list a client could not buy from', function (): void {
    $admin = platformUser();

    $this->actingAs($admin)
        ->put(route('admin.pricing.update'), dynamicPricingPayload([
            'rates' => ['sms' => 0],
        ]))
        ->assertSessionHasErrors('rates.sms');

    $this->actingAs($admin)
        ->put(route('admin.pricing.update'), dynamicPricingPayload([
            'limits' => ['min' => 500, 'max' => 100],
        ]))
        ->assertSessionHasErrors('limits.max');

    $this->actingAs($admin)
        ->put(route('admin.pricing.update'), dynamicPricingPayload([
            'slider' => ['min' => 900, 'max' => 500],
        ]))
        ->assertSessionHasErrors('slider.max');

    expect(PlatformSetting::query()->where('group', 'billing')->count())->toBe(0);
});

it('keeps tenant users out of the pricing screen', function (): void {
    $this->actingAs($this->user)
        ->get(route('admin.pricing.index'))
        ->assertForbidden();

    $this->actingAs($this->user)
        ->put(route('admin.pricing.update'), dynamicPricingPayload())
        ->assertForbidden();

    expect(PlatformSetting::query()->where('group', 'billing')->count())->toBe(0);
});

it('draws the slider on the mobile money form', function (): void {
    $this->actingAs($this->user)
        ->get(route('wallet.mobile-money.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('wallet/mobile-money')
            ->where('dynamic_pricing', true)
            ->has('rates', 3)
            ->where('rates.0.unit_price', 0.04)
            ->where('slider.default', 100)
            ->where('selected_amount', null));

    $this->actingAs($this->user)
        ->get(route('wallet.mobile-money.create', ['amount' => 250]))
        ->assertInertia(fn (Assert $page) => $page->where('selected_amount', 250));
});

it('prompts the wallet for the amount the client chose', function (): void {
    config()->set('billing.driver', 'paystack');
    config()->set('billing.paystack.base_url', 'https://api.paystack.co');
    config()->set('billing.paystack.secret_key', 'sk_test_dynamic_pricing');

    Http::fake([
        'api.paystack.co/charge' => Http::response([
            'status' => true,
            'message' => 'Charge attempted',
            'data' => [
                'status' => 'send_otp',
                'display_text' => 'Please enter OTP sent to 0244215189',
            ],
        ]),
    ]);

    $this->actingAs($this->user)
        ->post(route('wallet.mobile-money.store'), [
            'amount' => 200,
            'phone' => '0244215189',
            'provider' => 'mtn',
        ])
        ->assertRedirect();

    $payment = Payment::query()->sole();

    expect($payment->status)->toBe(PaymentStatus::Pending)
        ->and($payment->package_key)->toBe(CheckoutService::CUSTOM_PACKAGE)
        ->and((float) $payment->amount)->toBe(200.0);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.paystack.co/charge'
        && $request['amount'] === 20000
        && $request['currency'] === 'GHS');
});
