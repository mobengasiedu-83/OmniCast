<?php

use App\Enums\PaymentStatus;
use App\Enums\WalletTransactionType;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\WalletTransaction;
use App\Services\Billing\CheckoutService;
use App\Services\Billing\PaymentVerification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    config()->set('billing.driver', 'log');

    [$this->client, $this->wallet, $this->user] = clientWithWallet(balance: 10.0);
});

it('registers a pending top up and hands the client to the gateway', function (): void {
    $response = $this->actingAs($this->user)
        ->post(route('wallet.checkout.store'), ['package' => 'growth']);

    $payment = Payment::query()->sole();

    $response->assertRedirect(route('wallet.checkout.callback', ['reference' => $payment->reference]));

    expect($payment->status)->toBe(PaymentStatus::Pending)
        ->and($payment->provider)->toBe('log')
        ->and($payment->package_key)->toBe('growth')
        ->and((float) $payment->amount)->toBe(243.75)
        ->and((float) $payment->amount_paid)->toBe(0.0)
        ->and($payment->wallet_id)->toBe($this->wallet->id)
        ->and($payment->user_id)->toBe($this->user->id)
        ->and($payment->authorization_url)->toContain('wallet/checkout/callback')
        ->and((float) $this->wallet->refresh()->balance)->toBe(10.0);
});

it('refuses a package that is not on the catalogue', function (): void {
    $this->actingAs($this->user)
        ->post(route('wallet.checkout.store'), ['package' => 'gold-plated'])
        ->assertSessionHasErrors('package');

    expect(Payment::query()->count())->toBe(0);
});

it('credits the wallet and raises an invoice once the gateway confirms', function (): void {
    $payment = Payment::factory()->for($this->client)->create([
        'wallet_id' => $this->wallet->id,
        'provider' => 'log',
    ]);

    $this->actingAs($this->user)
        ->get(route('wallet.checkout.callback', ['reference' => $payment->reference]))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('toast');

    $payment->refresh();

    expect($payment->status)->toBe(PaymentStatus::Paid)
        ->and((float) $payment->amount_paid)->toBe((float) $payment->amount)
        ->and($payment->paid_at)->not->toBeNull()
        ->and((float) $this->wallet->refresh()->balance)->toBe(round(10.0 + (float) $payment->amount, 2));

    $transaction = WalletTransaction::query()->sole();
    $invoice = Invoice::query()->sole();

    expect($transaction->type)->toBe(WalletTransactionType::Credit)
        ->and($transaction->payment_id)->toBe($payment->id)
        ->and((float) $transaction->amount)->toBe((float) $payment->amount)
        ->and($invoice->payment_id)->toBe($payment->id)
        ->and((float) $invoice->total)->toBe((float) $payment->amount);
});

it('never credits the wallet twice when the callback is replayed', function (): void {
    $payment = Payment::factory()->for($this->client)->create([
        'wallet_id' => $this->wallet->id,
        'provider' => 'log',
    ]);

    $callback = route('wallet.checkout.callback', ['reference' => $payment->reference]);

    $this->actingAs($this->user)->get($callback)->assertRedirect(route('dashboard'));
    $this->actingAs($this->user)->get($callback)->assertRedirect(route('dashboard'));

    expect(WalletTransaction::query()->count())->toBe(1)
        ->and(Invoice::query()->count())->toBe(1)
        ->and((float) $this->wallet->refresh()->balance)->toBe(round(10.0 + (float) $payment->amount, 2));
});

it('refuses the callback reference of another tenant top up', function (): void {
    [$otherClient] = clientWithWallet();

    $payment = Payment::factory()->for($otherClient)->create(['provider' => 'log']);

    $this->actingAs($this->user)
        ->get(route('wallet.checkout.callback', ['reference' => $payment->reference]))
        ->assertForbidden();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending)
        ->and(WalletTransaction::query()->count())->toBe(0);
});

it('abandons an open checkout the client closed', function (): void {
    $payment = Payment::factory()->for($this->client)->create(['provider' => 'log']);

    $this->actingAs($this->user)
        ->get(route('wallet.checkout.cancel', $payment))
        ->assertRedirect(route('wallet.index'))
        ->assertSessionHas('toast');

    expect($payment->refresh()->status)->toBe(PaymentStatus::Abandoned)
        ->and((float) $this->wallet->refresh()->balance)->toBe(10.0)
        ->and(WalletTransaction::query()->count())->toBe(0);
});

it('fails a top up the gateway under-collected', function (): void {
    $payment = Payment::factory()->for($this->client)->create(['provider' => 'log']);

    app(CheckoutService::class)->settle($payment, new PaymentVerification(
        successful: true,
        amount: 25.0,
        currency: 'GHS',
        providerReference: $payment->reference,
        channel: 'card',
    ));

    $payment->refresh();

    expect($payment->status)->toBe(PaymentStatus::Failed)
        ->and($payment->failure_reason)->toContain('against a package price')
        ->and((float) $this->wallet->refresh()->balance)->toBe(10.0)
        ->and(WalletTransaction::query()->count())->toBe(0)
        ->and(Invoice::query()->count())->toBe(0);
});

it('shows the tenant its own wallet and ledger', function (): void {
    $this->actingAs($this->user)
        ->get(route('wallet.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('wallet/index')
            ->where('client.id', $this->client->id)
            ->where('wallet.balance', 10)
            ->where('currency', 'GHS')
            ->where('can.checkout', true)
            ->where('can.manual_credit', false)
            ->has('packages', 4),
        );
});
