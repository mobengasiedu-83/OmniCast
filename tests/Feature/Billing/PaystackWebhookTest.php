<?php

use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\WalletTransaction;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    config()->set('billing.driver', 'log');
    config()->set('billing.paystack.webhook_secret', 'paystack-webhook-secret');

    [$this->client, $this->wallet, $this->user] = clientWithWallet(balance: 25.0);

    $this->payment = Payment::factory()->for($this->client)->create([
        'wallet_id' => $this->wallet->id,
        'provider' => 'log',
    ]);

    /**
     * Post a charge event the way Paystack signs and sends it.
     *
     * @param  array<string, mixed>  $payload
     */
    $this->postEvent = function (array $payload, ?string $secret = 'paystack-webhook-secret'): TestResponse {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        return $this->call(
            'POST',
            route('webhooks.paystack'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_PAYSTACK_SIGNATURE' => $secret === null ? '' : hash_hmac('sha512', $body, $secret),
            ],
            $body,
        );
    };
});

it('settles the top up a signed charge.success names', function (): void {
    ($this->postEvent)([
        'event' => 'charge.success',
        'data' => [
            'reference' => $this->payment->reference,
            'amount' => 24375,
            'currency' => 'GHS',
            'channel' => 'mobile_money',
            'status' => 'success',
        ],
    ])
        ->assertOk()
        ->assertJson(['status' => 'paid', 'reference' => $this->payment->reference]);

    $payment = $this->payment->refresh();

    expect($payment->status)->toBe(PaymentStatus::Paid)
        ->and($payment->channel)->toBe('log')
        ->and((float) $this->wallet->refresh()->balance)->toBe(round(25.0 + (float) $payment->amount, 2));

    expect(WalletTransaction::query()->sole()->payment_id)->toBe($payment->id)
        ->and(Invoice::query()->sole()->payment_id)->toBe($payment->id);
});

it('credits the wallet once when Paystack replays charge.success', function (): void {
    $payload = [
        'event' => 'charge.success',
        'data' => ['reference' => $this->payment->reference, 'status' => 'success'],
    ];

    ($this->postEvent)($payload)->assertOk();
    ($this->postEvent)($payload)->assertOk();

    expect(WalletTransaction::query()->count())->toBe(1)
        ->and(Invoice::query()->count())->toBe(1)
        ->and((float) $this->wallet->refresh()->balance)->toBe(round(25.0 + (float) $this->payment->amount, 2));
});

it('rejects a payload whose signature does not match', function (): void {
    ($this->postEvent)([
        'event' => 'charge.success',
        'data' => ['reference' => $this->payment->reference],
    ], secret: 'the-wrong-secret')
        ->assertForbidden();

    expect($this->payment->refresh()->status)->toBe(PaymentStatus::Pending)
        ->and((float) $this->wallet->refresh()->balance)->toBe(25.0)
        ->and(WalletTransaction::query()->count())->toBe(0);
});

it('rejects every webhook when no shared secret is configured', function (): void {
    config()->set('billing.paystack.webhook_secret', '');
    config()->set('billing.paystack.secret_key', '');

    ($this->postEvent)([
        'event' => 'charge.success',
        'data' => ['reference' => $this->payment->reference],
    ])
        ->assertForbidden();

    expect($this->payment->refresh()->status)->toBe(PaymentStatus::Pending);
});

it('verifies the signature with the secret key when no webhook secret is set', function (): void {
    config()->set('billing.paystack.webhook_secret', '');
    config()->set('billing.paystack.secret_key', 'sk_test_signing_key');

    ($this->postEvent)([
        'event' => 'charge.success',
        'data' => [
            'reference' => $this->payment->reference,
            'amount' => 24375,
            'currency' => 'GHS',
            'status' => 'success',
        ],
    ], secret: 'sk_test_signing_key')
        ->assertOk()
        ->assertJson(['status' => 'paid', 'reference' => $this->payment->reference]);

    expect($this->payment->refresh()->status)->toBe(PaymentStatus::Paid);
});

it('fails the payment when the gateway reports charge.failed', function (): void {
    ($this->postEvent)([
        'event' => 'charge.failed',
        'data' => [
            'reference' => $this->payment->reference,
            'gateway_response' => 'Insufficient funds',
            'status' => 'failed',
        ],
    ])
        ->assertOk()
        ->assertJson(['status' => 'failed']);

    expect($this->payment->refresh()->status)->toBe(PaymentStatus::Failed)
        ->and($this->payment->failure_reason)->toBe('Insufficient funds')
        ->and((float) $this->wallet->refresh()->balance)->toBe(25.0)
        ->and(WalletTransaction::query()->count())->toBe(0)
        ->and(Invoice::query()->count())->toBe(0);
});

it('ignores an event naming a reference it never created', function (): void {
    ($this->postEvent)([
        'event' => 'charge.success',
        'data' => ['reference' => 'OC-PAY-UNKNOWN'],
    ])
        ->assertOk()
        ->assertJson(['status' => 'ignored', 'reference' => null]);

    expect($this->payment->refresh()->status)->toBe(PaymentStatus::Pending)
        ->and((float) $this->wallet->refresh()->balance)->toBe(25.0);
});

it('ignores an event without a reference', function (): void {
    ($this->postEvent)(['event' => 'charge.success', 'data' => []])
        ->assertOk()
        ->assertJson(['status' => 'ignored']);

    expect(WalletTransaction::query()->count())->toBe(0);
});
