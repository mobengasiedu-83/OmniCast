<?php

namespace App\Services\Billing;

use App\Contracts\PaymentGateway;
use Illuminate\Contracts\Container\Container;

/**
 * Resolves the funding gateway named by `billing.driver`.
 *
 * An unconfigured gateway resolves to a disabled gateway that explains itself,
 * so the wallet screen can still render and tell the administrator what is
 * missing rather than failing with a 500.
 */
class PaymentGatewayManager
{
    public function __construct(protected Container $container) {}

    /**
     * The gateway a payment was registered with (or the configured default).
     */
    public function driver(?string $name = null): PaymentGateway
    {
        $name = mb_strtolower(trim($name ?? (string) config('billing.driver', 'paystack')));

        return match ($name) {
            'paystack' => $this->paystack(),
            'log' => $this->container->make(LogPaymentGateway::class),
            default => new DisabledPaymentGateway(
                $name === '' ? 'none' : $name,
                "The [{$name}] payment gateway is not supported. Set BILLING_DRIVER to paystack or log.",
            ),
        };
    }

    /**
     * Whether the configured gateway can actually take money right now.
     */
    public function isLive(): bool
    {
        $gateway = $this->driver();

        return $gateway instanceof PaystackGateway;
    }

    /**
     * Paystack needs a secret key before it can register a transaction.
     */
    protected function paystack(): PaymentGateway
    {
        if ((string) config('billing.paystack.secret_key') === '') {
            return new DisabledPaymentGateway(
                'paystack',
                'Paystack is not configured: set PAYSTACK_SECRET_KEY in the environment.',
            );
        }

        return $this->container->make(PaystackGateway::class);
    }
}
