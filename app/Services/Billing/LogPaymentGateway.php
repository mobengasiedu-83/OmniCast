<?php

namespace App\Services\Billing;

use App\Contracts\PaymentGateway;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;

/**
 * A development gateway: no money moves, the client is sent straight back to
 * the callback and the payment settles itself.
 *
 * `BILLING_DRIVER=log` keeps the whole funding flow (checkout record, ledger
 * credit, invoice) exercisable before a live Paystack key is issued.
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
        );
    }
}
