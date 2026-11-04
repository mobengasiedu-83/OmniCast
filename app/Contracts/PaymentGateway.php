<?php

namespace App\Contracts;

use App\Exceptions\PaymentGatewayException;
use App\Models\Payment;
use App\Services\Billing\PaymentInitiation;
use App\Services\Billing\PaymentIntent;
use App\Services\Billing\PaymentVerification;

/**
 * A hosted checkout a client is redirected to in order to fund a wallet.
 */
interface PaymentGateway
{
    /**
     * The keys written to `payments.provider`.
     */
    public function name(): string;

    /**
     * Register the payment with the gateway and return where to send the client.
     *
     * @throws PaymentGatewayException
     */
    public function initialize(PaymentIntent $intent): PaymentInitiation;

    /**
     * Ask the gateway what became of a payment we already registered.
     *
     * @throws PaymentGatewayException
     */
    public function verify(Payment $payment): PaymentVerification;
}
