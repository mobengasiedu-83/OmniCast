<?php

namespace App\Services\Billing;

use App\Contracts\PaymentGateway;
use App\Enums\MobileMoneyProvider;
use App\Exceptions\PaymentGatewayException;
use App\Models\Payment;

/**
 * Stands in for a gateway the platform is not configured to talk to yet.
 *
 * Starting a checkout fails with the reason the administrator has to fix, while
 * a verification reports an unsuccessful payment instead of moving money. A
 * direct mobile money charge is refused for the same reason, so the wallet
 * screen offers only the hosted checkout.
 */
class DisabledPaymentGateway implements PaymentGateway
{
    public function __construct(
        protected readonly string $name,
        protected readonly string $reason,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * {@inheritDoc}
     */
    public function initialize(PaymentIntent $intent): PaymentInitiation
    {
        throw new PaymentGatewayException($this->reason);
    }

    /**
     * {@inheritDoc}
     */
    public function verify(Payment $payment): PaymentVerification
    {
        return PaymentVerification::unsuccessful($this->reason);
    }

    /**
     * {@inheritDoc}
     */
    public function supportsMobileMoney(): bool
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function charge(PaymentIntent $intent, MobileMoneyNumber $number, MobileMoneyProvider $provider): MobileMoneyCharge
    {
        throw new PaymentGatewayException($this->reason);
    }

    /**
     * {@inheritDoc}
     */
    public function submitOtp(string $reference, string $otp): MobileMoneyCharge
    {
        throw new PaymentGatewayException($this->reason);
    }
}
