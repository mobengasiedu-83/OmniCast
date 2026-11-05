<?php

namespace App\Contracts;

use App\Enums\MobileMoneyProvider;
use App\Exceptions\PaymentGatewayException;
use App\Models\Payment;
use App\Services\Billing\MobileMoneyCharge;
use App\Services\Billing\MobileMoneyNumber;
use App\Services\Billing\PaymentInitiation;
use App\Services\Billing\PaymentIntent;
use App\Services\Billing\PaymentVerification;

/**
 * How a wallet is funded: a hosted checkout the client is redirected to, or a
 * mobile money charge this application prompts on its own form.
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

    /**
     * Whether this gateway can prompt a mobile money wallet from our own form.
     */
    public function supportsMobileMoney(): bool;

    /**
     * Ask the gateway to charge a mobile money wallet directly, without sending
     * the client to a hosted checkout.
     *
     * @throws PaymentGatewayException
     */
    public function charge(PaymentIntent $intent, MobileMoneyNumber $number, MobileMoneyProvider $provider): MobileMoneyCharge;

    /**
     * Answer the one time password the wallet sent to the payer.
     *
     * @throws PaymentGatewayException
     */
    public function submitOtp(string $reference, string $otp): MobileMoneyCharge;
}
