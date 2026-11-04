<?php

namespace App\Enums;

/**
 * The life of a wallet funding invoice.
 */
enum InvoiceStatus: string
{
    case Paid = 'paid';
    case Refunded = 'refunded';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Paid => 'Paid',
            self::Refunded => 'Refunded',
            self::Void => 'Void',
        };
    }
}
