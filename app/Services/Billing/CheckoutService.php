<?php

namespace App\Services\Billing;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentGatewayException;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Runs a wallet top up: open a payment, hand the client to the gateway, and
 * settle the ledger and the invoice once the money has actually arrived.
 *
 * Settlement is idempotent. A webhook may arrive after the client already came
 * back from the callback, and Paystack retries a webhook it did not get a 200
 * for, so only the first settlement credits the wallet.
 */
class CheckoutService
{
    public function __construct(
        protected WalletService $wallets,
        protected PaymentGatewayManager $gateways,
        protected PackageCatalogue $packages,
    ) {}

    /**
     * Register a top up and ask the gateway where to send the client.
     *
     * @throws ValidationException
     * @throws PaymentGatewayException
     */
    public function start(Client $client, ?User $user, string $packageKey, string $callbackUrl): Payment
    {
        $package = $this->packages->find($packageKey);

        if ($package === null) {
            throw ValidationException::withMessages([
                'package' => 'Choose one of the wallet top up packages.',
            ]);
        }

        $gateway = $this->gateways->driver();
        $wallet = $this->wallets->walletFor($client);

        $payment = Payment::query()->create([
            'client_id' => $client->id,
            'wallet_id' => $wallet->id,
            'user_id' => $user?->id,
            'reference' => $this->paymentReference(),
            'provider' => $gateway->name(),
            'status' => PaymentStatus::Pending,
            'currency' => $this->packages->currency(),
            'amount' => $package['price'],
            'amount_paid' => 0,
            'package_key' => $package['key'],
            'description' => $this->packages->describe($package['key']),
            'metadata' => [
                'package' => $package['key'],
                'client_code' => $client->code,
                'initiated_by' => $user?->email,
            ],
        ]);

        $intent = new PaymentIntent(
            reference: $payment->reference,
            email: $this->payerEmail($client, $user),
            amount: (float) $payment->amount,
            currency: $payment->currency,
            callbackUrl: $callbackUrl,
            description: $payment->description,
            metadata: [
                'payment_id' => $payment->id,
                'client_id' => $client->id,
                'package' => $package['key'],
            ],
            channels: array_values(array_map('strval', (array) config('billing.paystack.channels', []))),
        );

        try {
            $initiation = $gateway->initialize($intent);
        } catch (PaymentGatewayException $exception) {
            $payment->forceFill([
                'status' => PaymentStatus::Failed,
                'failure_reason' => $exception->getMessage(),
            ])->save();

            throw $exception;
        }

        $payment->forceFill([
            'authorization_url' => $initiation->authorizationUrl,
            'access_code' => $initiation->accessCode,
            'provider_reference' => $initiation->providerReference,
        ])->save();

        return $payment->refresh();
    }

    /**
     * Ask the gateway what became of a payment and settle it.
     *
     * @throws PaymentGatewayException
     */
    public function verify(Payment $payment): Payment
    {
        if ($payment->isSettled()) {
            return $payment;
        }

        return $this->settle(
            $payment,
            $this->gateways->driver($payment->provider)->verify($payment),
        );
    }

    /**
     * Apply the gateway's answer to a payment: credit the wallet and raise the
     * invoice the first time, and do nothing at all the second time.
     */
    public function settle(Payment $payment, PaymentVerification $verification): Payment
    {
        return DB::transaction(function () use ($payment, $verification): Payment {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->isSettled()) {
                Log::channel('billing')->info('payment.settlement.ignored', [
                    'reference' => $payment->reference,
                    'status' => $payment->status->value,
                ]);

                return $payment;
            }

            if (! $verification->successful) {
                return $this->fail($payment, $verification->failureReason ?? 'The payment was not completed.', $verification);
            }

            if (! $verification->covers((float) $payment->amount)) {
                return $this->fail(
                    $payment,
                    'The gateway collected '.$payment->currency.' '.number_format($verification->amount, 2)
                        .' against a package price of '.$payment->currency.' '.number_format((float) $payment->amount, 2).'.',
                    $verification,
                );
            }

            $payment->forceFill([
                'status' => PaymentStatus::Paid,
                'amount_paid' => $verification->amount,
                'provider_reference' => $verification->providerReference ?? $payment->provider_reference,
                'channel' => $verification->channel,
                'failure_reason' => null,
                'paid_at' => $verification->paidAt ?? now(),
                'metadata' => array_merge((array) $payment->metadata, [
                    'verification' => $verification->raw,
                ]),
            ])->save();

            $this->wallets->topUp(
                wallet: $this->wallets->walletFor($payment->client),
                amount: (float) $payment->amount,
                description: $payment->description,
                reference: $payment->reference,
                payment: $payment,
            );

            $this->issueInvoice($payment);

            Log::channel('billing')->info('payment.settled', [
                'reference' => $payment->reference,
                'client_id' => $payment->client_id,
                'amount' => (float) $payment->amount,
                'channel' => $payment->channel,
            ]);

            return $payment->refresh();
        });
    }

    /**
     * Abandon a checkout the client walked away from.
     */
    public function abandon(Payment $payment): Payment
    {
        if (! $payment->status->isOpen()) {
            return $payment;
        }

        $payment->forceFill([
            'status' => PaymentStatus::Abandoned,
            'failure_reason' => 'The checkout was closed before it was paid.',
        ])->save();

        return $payment->refresh();
    }

    /**
     * Handle one verified gateway event.
     *
     * `charge.success` is settled against the gateway's own verify endpoint
     * rather than the payload, so a payload that was tampered with before the
     * signature was checked still cannot credit a wallet.
     */
    public function handleEvent(string $event, array $payload): ?Payment
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $reference = $data['reference'] ?? null;

        if (! is_string($reference) || $reference === '') {
            Log::channel('billing')->warning('payment.event.without_reference', ['event' => $event]);

            return null;
        }

        $payment = Payment::query()->where('reference', $reference)->first();

        if ($payment === null) {
            Log::channel('billing')->warning('payment.event.unknown_reference', [
                'event' => $event,
                'reference' => $reference,
            ]);

            return null;
        }

        return match ($event) {
            'charge.success' => $this->verify($payment),
            'charge.failed' => $this->settle($payment, new PaymentVerification(
                successful: false,
                amount: $payment->isSettled() ? (float) $payment->amount_paid : 0.0,
                currency: $payment->currency,
                providerReference: $reference,
                failureReason: $this->failureMessage($data),
                raw: $data,
            )),
            default => $payment,
        };
    }

    /**
     * Raise the paid invoice for a settled payment, once.
     */
    public function issueInvoice(Payment $payment): Invoice
    {
        $existing = Invoice::query()->where('payment_id', $payment->id)->first();

        if ($existing !== null) {
            return $existing;
        }

        $amount = round((float) $payment->amount, 2);

        return Invoice::query()->create([
            'client_id' => $payment->client_id,
            'payment_id' => $payment->id,
            'number' => $this->invoiceNumber(),
            'status' => InvoiceStatus::Paid,
            'currency' => $payment->currency,
            'subtotal' => $amount,
            'tax' => 0,
            'total' => $amount,
            'lines' => [[
                'description' => $payment->description,
                'quantity' => 1,
                'unit_price' => $amount,
                'amount' => $amount,
            ]],
            'metadata' => [
                'payment_reference' => $payment->reference,
                'channel' => $payment->channel,
                'package' => $payment->package_key,
            ],
            'issued_at' => $payment->paid_at ?? now(),
            'paid_at' => $payment->paid_at ?? now(),
        ]);
    }

    /**
     * Record why a payment did not settle.
     */
    protected function fail(Payment $payment, string $reason, PaymentVerification $verification): Payment
    {
        $payment->forceFill([
            'status' => PaymentStatus::Failed,
            'amount_paid' => $verification->amount,
            'provider_reference' => $verification->providerReference ?? $payment->provider_reference,
            'channel' => $verification->channel ?? $payment->channel,
            'failure_reason' => Str::limit($reason, 250, ''),
            'metadata' => array_merge((array) $payment->metadata, [
                'verification' => $verification->raw,
            ]),
        ])->save();

        Log::channel('billing')->warning('payment.failed', [
            'reference' => $payment->reference,
            'reason' => $reason,
        ]);

        return $payment->refresh();
    }

    /**
     * The address the gateway contacts about the payment. Every gateway needs
     * one, and a tenant is not guaranteed to have stored its own.
     */
    protected function payerEmail(Client $client, ?User $user): string
    {
        $candidates = [$client->email, $user?->email, config('billing.support_email')];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && filter_var($candidate, FILTER_VALIDATE_EMAIL) !== false) {
                return $candidate;
            }
        }

        return 'billing@omnicast.app';
    }

    /**
     * The reference the gateway, the ledger and the invoice all share.
     */
    protected function paymentReference(): string
    {
        do {
            $reference = 'OC-PAY-'.now()->format('ymd').'-'.Str::upper(Str::random(6));
        } while (Payment::query()->where('reference', $reference)->exists());

        return $reference;
    }

    /**
     * A receipt number unique to the day the invoice was issued.
     */
    protected function invoiceNumber(): string
    {
        do {
            $number = 'INV-'.now()->format('Ymd').'-'.Str::upper(Str::random(5));
        } while (Invoice::query()->where('number', $number)->exists());

        return $number;
    }

    /**
     * What the gateway said went wrong, in the client's words.
     *
     * @param  array<string, mixed>  $data
     */
    protected function failureMessage(array $data): string
    {
        $message = $data['gateway_response'] ?? $data['message'] ?? null;

        return is_string($message) && $message !== '' ? $message : 'The payment was not completed.';
    }
}
