<?php

use App\Enums\PaymentStatus;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    config()->set('billing.driver', 'paystack');
    config()->set('billing.paystack.base_url', 'https://api.paystack.co');
    config()->set('billing.paystack.secret_key', 'sk_test_mobile_money');

    [$this->client, $this->wallet, $this->user] = clientWithWallet(balance: 10.0);
});

it('prompts the wallet and asks for the code the network sends', function (): void {
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

    $response = $this->actingAs($this->user)
        ->post(route('wallet.mobile-money.store'), [
            'package' => 'growth',
            'phone' => '+233 24 421 5189',
            'provider' => 'mtn',
        ]);

    $payment = Payment::query()->sole();

    $response->assertRedirect(route('wallet.mobile-money.show', $payment));

    expect($payment->status)->toBe(PaymentStatus::Pending)
        ->and($payment->provider)->toBe('paystack')
        ->and($payment->channel)->toBe('mobile_money')
        ->and($payment->mobileMoneyNumber())->toBe('0244215189')
        ->and(data_get($payment->mobileMoney(), 'requires_otp'))->toBeTrue()
        ->and((float) $this->wallet->refresh()->balance)->toBe(10.0)
        ->and(WalletTransaction::query()->count())->toBe(0);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.paystack.co/charge'
        && $request['amount'] === 24375
        && $request['currency'] === 'GHS'
        && $request['mobile_money']['phone'] === '0244215189'
        && $request['mobile_money']['provider'] === 'mtn');
});

it('credits the wallet once the network accepts the code', function (): void {
    $payment = pendingMobileMoneyPayment($this->client, $this->wallet);

    Http::fake([
        'api.paystack.co/charge/submit_otp' => Http::response([
            'status' => true,
            'message' => 'Charge attempted',
            'data' => ['status' => 'success'],
        ]),
        'api.paystack.co/transaction/verify/*' => Http::response([
            'status' => true,
            'message' => 'Verification successful',
            'data' => [
                'reference' => $payment->reference,
                'status' => 'success',
                'amount' => 24375,
                'currency' => 'GHS',
                'channel' => 'mobile_money',
                'paid_at' => now()->toIso8601String(),
            ],
        ]),
    ]);

    $this->actingAs($this->user)
        ->post(route('wallet.mobile-money.otp', $payment), ['otp' => '123456'])
        ->assertRedirect(route('dashboard'));

    $payment->refresh();

    expect($payment->status)->toBe(PaymentStatus::Paid)
        ->and((float) $payment->amount_paid)->toBe((float) $payment->amount)
        ->and((float) $this->wallet->refresh()->balance)->toBe(round(10.0 + (float) $payment->amount, 2));

    expect(WalletTransaction::query()->sole()->payment_id)->toBe($payment->id)
        ->and(Invoice::query()->sole()->payment_id)->toBe($payment->id);
});

it('leaves the charge open when the network rejects the code', function (): void {
    $payment = pendingMobileMoneyPayment($this->client, $this->wallet);

    Http::fake([
        'api.paystack.co/charge/submit_otp' => Http::response([
            'status' => true,
            'message' => 'Charge attempted',
            'data' => ['status' => 'failed', 'display_text' => 'The OTP entered is incorrect'],
        ]),
    ]);

    $this->actingAs($this->user)
        ->post(route('wallet.mobile-money.otp', $payment), ['otp' => '000000'])
        ->assertSessionHasErrors('otp');

    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending)
        ->and((float) $this->wallet->refresh()->balance)->toBe(10.0)
        ->and(WalletTransaction::query()->count())->toBe(0);
});

it('keeps the charge open while the network has not answered', function (): void {
    $payment = pendingMobileMoneyPayment($this->client, $this->wallet);

    Http::fake([
        'api.paystack.co/transaction/verify/*' => Http::response([
            'status' => true,
            'message' => 'Verification successful',
            'data' => [
                'reference' => $payment->reference,
                'status' => 'ongoing',
                'amount' => 24375,
                'currency' => 'GHS',
                'gateway_response' => 'Approve the prompt on your phone',
            ],
        ]),
    ]);

    $this->actingAs($this->user)
        ->post(route('wallet.mobile-money.refresh', $payment))
        ->assertRedirect(route('wallet.mobile-money.show', $payment));

    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending)
        ->and((float) $this->wallet->refresh()->balance)->toBe(10.0)
        ->and(WalletTransaction::query()->count())->toBe(0);
});

it('refuses a number that is not a Ghanaian wallet', function (): void {
    Http::fake();

    $this->actingAs($this->user)
        ->post(route('wallet.mobile-money.store'), [
            'package' => 'growth',
            'phone' => '12345',
            'provider' => 'mtn',
        ])
        ->assertSessionHasErrors('phone');

    expect(Payment::query()->count())->toBe(0);

    Http::assertNothingSent();
});

it('fails the top up when the gateway refuses the charge', function (): void {
    Http::fake([
        'api.paystack.co/charge' => Http::response([
            'status' => false,
            'message' => 'Mobile money is not enabled on this integration',
        ], 400),
    ]);

    $this->actingAs($this->user)
        ->post(route('wallet.mobile-money.store'), [
            'package' => 'growth',
            'phone' => '0244215189',
            'provider' => 'mtn',
        ])
        ->assertSessionHas('toast');

    expect(Payment::query()->sole()->status)->toBe(PaymentStatus::Failed)
        ->and((float) $this->wallet->refresh()->balance)->toBe(10.0)
        ->and(WalletTransaction::query()->count())->toBe(0);
});

it('refuses the charge of another tenant', function (): void {
    [$otherClient, $otherWallet] = clientWithWallet();

    $payment = pendingMobileMoneyPayment($otherClient, $otherWallet);

    $this->actingAs($this->user)
        ->get(route('wallet.mobile-money.show', $payment))
        ->assertForbidden();

    $this->actingAs($this->user)
        ->post(route('wallet.mobile-money.otp', $payment), ['otp' => '123456'])
        ->assertForbidden();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending)
        ->and(WalletTransaction::query()->count())->toBe(0);
});

it('shows the form with the networks it can prompt', function (): void {
    $this->actingAs($this->user)
        ->get(route('wallet.mobile-money.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('wallet/mobile-money')
            ->where('client.id', $this->client->id)
            ->where('currency', 'GHS')
            ->where('payment', null)
            ->where('gateway.mobile_money', true)
            ->has('packages', 4)
            ->has('providers', 3),
        );
});

it('prompts and settles on the log driver without a live key', function (): void {
    config()->set('billing.driver', 'log');

    Http::fake();

    $this->actingAs($this->user)
        ->post(route('wallet.mobile-money.store'), [
            'package' => 'growth',
            'phone' => '0244215189',
            'provider' => 'mtn',
        ])
        ->assertRedirect();

    $payment = Payment::query()->sole();

    expect($payment->status)->toBe(PaymentStatus::Pending)
        ->and(data_get($payment->mobileMoney(), 'requires_otp'))->toBeTrue();

    $this->actingAs($this->user)
        ->post(route('wallet.mobile-money.otp', $payment), ['otp' => '123456'])
        ->assertRedirect(route('dashboard'));

    expect($payment->refresh()->status)->toBe(PaymentStatus::Paid)
        ->and((float) $this->wallet->refresh()->balance)->toBe(round(10.0 + (float) $payment->amount, 2));

    Http::assertNothingSent();
});

/**
 * A mobile money charge already registered with the gateway, waiting for the
 * code the network sent to the handset.
 */
function pendingMobileMoneyPayment(Client $client, Wallet $wallet): Payment
{
    return Payment::factory()->for($client)->create([
        'wallet_id' => $wallet->id,
        'provider' => 'paystack',
        'channel' => 'mobile_money',
        'status' => PaymentStatus::Pending,
        'metadata' => [
            'mobile_money' => [
                'phone' => '0244215189',
                'provider' => 'mtn',
                'provider_label' => 'MTN Mobile Money',
                'status' => 'send_otp',
                'requires_otp' => true,
                'message' => 'Please enter OTP sent to 0244215189',
            ],
        ],
    ]);
}
