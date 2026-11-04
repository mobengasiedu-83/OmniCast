<?php

namespace App\Enums;

/**
 * The life of a wallet top-up payment.
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Abandoned = 'abandoned';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Paid => 'Paid',
            self::Failed => 'Failed',
            self::Abandoned => 'Abandoned',
            self::Refunded => 'Refunded',
        };
    }

    /**
     * Whether the payment has been settled and must not be charged a second time.
     */
    public function isSettled(): bool
    {
        return in_array($this, [self::Paid, self::Refunded], true);
    }

    /**
     * Whether the client can still be redirected to a checkout for this payment.
     */
    public function isOpen(): bool
    {
        return $this === self::Pending;
    }
}
