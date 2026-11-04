<?php

namespace App\Services\Billing;

use App\Contracts\PaymentGateway;
use App\Exceptions\PaymentGatewayException;
use App\Models\Payment;

/**
 * Stands in for a gateway the platform is not configured to talk to yet.
 *
 * Starting a checkout fails with the reason the administrator has to fix, while
 * a verification reports an unsuccessful payment instead of moving money.
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
}
