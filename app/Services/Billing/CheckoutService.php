<?php

namespace App\Services\Billing;

use App\Enums\InvoiceStatus;
use App\Enums\MobileMoneyProvider;
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
        $package = $this->package($packageKey);

        $gateway = $this->gateways->driver();

        $payment = $this->openPayment($client, $user, $package, $gateway->name());

        try {
            $initiation = $gateway->initialize(
                $this->intentFor($payment, $client, $user, $package['key'], $callbackUrl),
            );
        } catch (PaymentGatewayException $exception) {
            $this->markFailed($payment, $exception->getMessage());

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
     * Prompt a mobile money wallet directly, without a hosted checkout page.
     *
     * The payment stays open until the wallet answers: the client may have to
     * submit the code the wallet texted them, or approve a prompt on the
     * handset. Either way only `verify()` is able to credit the wallet.
     *
     * @throws ValidationException
     * @throws PaymentGatewayException
     */
    public function startMobileMoney(
        Client $client,
        ?User $user,
        string $packageKey,
        MobileMoneyNumber $number,
        MobileMoneyProvider $provider,
    ): Payment {
        $package = $this->package($packageKey);

        $gateway = $this->gateways->driver();

        if (! $gateway->supportsMobileMoney()) {
            throw new PaymentGatewayException(
                'The '.$gateway->name().' gateway cannot prompt a mobile money wallet. Pay on the hosted checkout instead.',
            );
        }

        $payment = $this->openPayment($client, $user, $package, $gateway->name());

        $this->rememberNumber($payment, $number, $provider);

        try {
            $charge = $gateway->charge(
                $this->intentFor($payment, $client, $user, $package['key'], route('wallet.mobile-money.show', $payment)),
                $number,
                $provider,
            );
        } catch (PaymentGatewayException $exception) {
            $this->markFailed($payment, $exception->getMessage());

            throw $exception;
        }

        $payment = $this->recordCharge($payment, $charge);

        if ($charge->isFailed()) {
            return $this->markFailed($payment, $charge->message);
        }

        return $charge->isSuccessful() ? $this->verify($payment) : $payment;
    }

    /**
     * Answer the code a mobile money wallet sent to the payer.
     *
     * A code the wallet rejects leaves the payment open, so the client can type
     * the next one; only the gateway's verification can close the payment.
     *
     * @throws ValidationException
     * @throws PaymentGatewayException
     */
    public function submitOtp(Payment $payment, string $otp): Payment
    {
        if (! $payment->status->isOpen()) {
            throw ValidationException::withMessages([
                'otp' => 'This payment is no longer waiting for a one time password.',
            ]);
        }

        $gateway = $this->gateways->driver($payment->provider);

        if (! $gateway->supportsMobileMoney()) {
            throw new PaymentGatewayException(
                'The '.$gateway->name().' gateway cannot prompt a mobile money wallet.',
            );
        }

        try {
            $charge = $gateway->submitOtp($payment->reference, $otp);
        } catch (PaymentGatewayException $exception) {
            throw ValidationException::withMessages(['otp' => $exception->getMessage()]);
        }

        $payment = $this->recordCharge($payment, $charge);

        if ($charge->isFailed()) {
            throw ValidationException::withMessages(['otp' => $charge->message]);
        }

        return $this->verify($payment);
    }

    /**
     * Ask the gateway what became of a payment and settle it.
     *
     * A wallet that has not answered yet (`pending`, `pay_offline`, `send_otp`)
     * leaves the payment open: it has refused nothing, so failing it would
     * throw away a charge the client can still approve on the phone.
     *
     * @throws PaymentGatewayException
     */
    public function verify(Payment $payment): Payment
    {
        if ($payment->isSettled()) {
            return $payment;
        }

        $verification = $this->gateways->driver($payment->provider)->verify($payment);

        if (! $verification->successful && $verification->isPending()) {
            return $this->hold($payment, $verification);
        }

        return $this->settle($payment, $verification);
    }

    /**
     * Keep a payment open while the wallet still has to answer it.
     */
    protected function hold(Payment $payment, PaymentVerification $verification): Payment
    {
        $metadata = array_merge((array) $payment->metadata, [
            'verification' => $verification->raw,
        ]);

        if ($payment->isMobileMoney()) {
            $mobileMoney = $this->mobileMoneyMetadata($payment);

            if ($verification->status !== null) {
                $mobileMoney['status'] = $verification->status;
            }

            if ($verification->failureReason !== null) {
                $mobileMoney['message'] = $verification->failureReason;
            }

            $mobileMoney['checked_at'] = now()->toIso8601String();

            $metadata['mobile_money'] = $mobileMoney;
        }

        $payment->forceFill([
            'provider_reference' => $verification->providerReference ?? $payment->provider_reference,
            'channel' => $verification->channel ?? $payment->channel,
            'metadata' => $metadata,
        ])->save();

        Log::channel('billing')->info('payment.still.pending', [
            'reference' => $payment->reference,
            'status' => $verification->status,
        ]);

        return $payment->refresh();
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
     *
     * @param  array<string, mixed>  $payload
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
     * The catalogue entry a top up is bought from.
     *
     * @return array{key: string, name: string, price: float, popular: bool, tagline: string, units: list<array{service: string, label: string, quantity: int, formatted: string}>}
     *
     * @throws ValidationException
     */
    protected function package(string $packageKey): array
    {
        $package = $this->packages->findPurchasable($packageKey);

        if ($package === null) {
            throw ValidationException::withMessages([
                'package' => 'Choose one of the wallet top up packages.',
            ]);
        }

        return $package;
    }

    /**
     * Write the pending payment the charge is carried on.
     *
     * @param  array<string, mixed>  $package
     */
    protected function openPayment(Client $client, ?User $user, array $package, string $provider): Payment
    {
        return Payment::query()->create([
            'client_id' => $client->id,
            'wallet_id' => $this->wallets->walletFor($client)->id,
            'user_id' => $user?->id,
            'reference' => $this->paymentReference(),
            'provider' => $provider,
            'status' => PaymentStatus::Pending,
            'currency' => $this->packages->currency(),
            'amount' => $package['price'],
            'amount_paid' => 0,
            'package_key' => $package['key'],
            'description' => $this->packages->describe((string) $package['key']),
            'metadata' => [
                'package' => $package['key'],
                'client_code' => $client->code,
                'initiated_by' => $user?->email,
            ],
        ]);
    }

    /**
     * What the gateway is told about a payment we registered.
     */
    protected function intentFor(Payment $payment, Client $client, ?User $user, string $packageKey, string $callbackUrl): PaymentIntent
    {
        return new PaymentIntent(
            reference: $payment->reference,
            email: $this->payerEmail($client, $user),
            amount: (float) $payment->amount,
            currency: $payment->currency,
            callbackUrl: $callbackUrl,
            description: $payment->description,
            metadata: [
                'payment_id' => $payment->id,
                'client_id' => $client->id,
                'package' => $packageKey,
            ],
            channels: array_values(array_map('strval', (array) config('billing.paystack.channels', []))),
        );
    }

    /**
     * Remember the wallet the client asked us to charge.
     */
    protected function rememberNumber(Payment $payment, MobileMoneyNumber $number, MobileMoneyProvider $provider): void
    {
        $payment->forceFill([
            'channel' => 'mobile_money',
            'metadata' => array_merge((array) $payment->metadata, [
                'mobile_money' => [
                    'phone' => $number->local,
                    'provider' => $provider->value,
                    'provider_label' => $provider->label(),
                ],
            ]),
        ])->save();
    }

    /**
     * Write down what the wallet answered, so the checkout page can show it.
     */
    protected function recordCharge(Payment $payment, MobileMoneyCharge $charge): Payment
    {
        $payment->forceFill([
            'provider_reference' => $charge->reference,
            'metadata' => array_merge((array) $payment->metadata, [
                'mobile_money' => array_merge($this->mobileMoneyMetadata($payment), [
                    'status' => $charge->status,
                    'message' => $charge->message,
                    'requires_otp' => $charge->requiresOtp(),
                    'updated_at' => now()->toIso8601String(),
                    'response' => $charge->raw,
                ]),
            ]),
        ])->save();

        Log::channel('billing')->info('payment.charge.recorded', [
            'reference' => $payment->reference,
            'status' => $charge->status,
        ]);

        return $payment->refresh();
    }

    /**
     * The mobile money block already written on a payment.
     *
     * @return array<string, mixed>
     */
    protected function mobileMoneyMetadata(Payment $payment): array
    {
        $mobileMoney = $payment->metadata['mobile_money'] ?? null;

        return is_array($mobileMoney) ? $mobileMoney : [];
    }

    /**
     * Close a payment the gateway refused.
     */
    protected function markFailed(Payment $payment, string $reason): Payment
    {
        $payment->forceFill([
            'status' => PaymentStatus::Failed,
            'failure_reason' => Str::limit($reason, 250, ''),
        ])->save();

        Log::channel('billing')->warning('payment.failed', [
            'reference' => $payment->reference,
            'reason' => $reason,
        ]);

        return $payment->refresh();
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
