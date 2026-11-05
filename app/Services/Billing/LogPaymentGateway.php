<?php

namespace App\Services\Billing;

use App\Contracts\PaymentGateway;
use App\Enums\MobileMoneyProvider;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;

/**
 * A development gateway: no money moves, the client is sent straight back to
 * the callback and the payment settles itself.
 *
 * `BILLING_DRIVER=log` keeps the whole funding flow (checkout record, ledger
 * credit, invoice) exercisable before a live Paystack key is issued. A direct
 * mobile money charge always answers "send_otp" and accepts any six digits, so
 * the prompt and the code forms are exercisable too.
 */
class LogPaymentGateway implements PaymentGateway
{
    /**
     * {@inheritDoc}
     */
    public function name(): string
    {
        return 'log';
    }

    /**
     * {@inheritDoc}
     */
    public function initialize(PaymentIntent $intent): PaymentInitiation
    {
        Log::channel('billing')->info('log.checkout.initialized', [
            'reference' => $intent->reference,
            'email' => $intent->email,
            'amount' => $intent->amount,
            'currency' => $intent->currency,
        ]);

        return new PaymentInitiation(
            authorizationUrl: $intent->callbackUrl.'?reference='.rawurlencode($intent->reference),
            accessCode: 'log-'.mb_substr($intent->reference, -8),
            providerReference: $intent->reference,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function verify(Payment $payment): PaymentVerification
    {
        Log::channel('billing')->info('log.checkout.verified', [
            'reference' => $payment->reference,
            'amount' => (float) $payment->amount,
        ]);

        return new PaymentVerification(
            successful: true,
            amount: (float) $payment->amount,
            currency: $payment->currency,
            providerReference: $payment->reference,
            channel: 'log',
            paidAt: now(),
            status: 'success',
        );
    }

    /**
     * {@inheritDoc}
     */
    public function supportsMobileMoney(): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function charge(PaymentIntent $intent, MobileMoneyNumber $number, MobileMoneyProvider $provider): MobileMoneyCharge
    {
        Log::channel('billing')->info('log.charge.started', [
            'reference' => $intent->reference,
            'amount' => $intent->amount,
            'currency' => $intent->currency,
            'phone' => $number->local,
            'provider' => $provider->value,
        ]);

        return new MobileMoneyCharge(
            status: 'send_otp',
            reference: $intent->reference,
            message: 'Enter any six digits to approve this simulated '.$provider->label().' charge.',
        );
    }

    /**
     * {@inheritDoc}
     */
    public function submitOtp(string $reference, string $otp): MobileMoneyCharge
    {
        Log::channel('billing')->info('log.charge.otp_submitted', [
            'reference' => $reference,
        ]);

        return new MobileMoneyCharge(
            status: 'success',
            reference: $reference,
            message: 'The simulated wallet approved the payment.',
        );
    }
}
