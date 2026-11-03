<?php

namespace App\Enums;

/**
 * The entry types written to the unified client ledger.
 */
enum WalletTransactionType: string
{
    case Credit = 'credit';
    case Debit = 'debit';
    case Reservation = 'reservation';
    case Release = 'release';
    case Refund = 'refund';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::Credit => 'Credit',
            self::Debit => 'Debit',
            self::Reservation => 'Reservation',
            self::Release => 'Release',
            self::Refund => 'Refund',
            self::Adjustment => 'Adjustment',
        };
    }

    public function increasesAvailableBalance(): bool
    {
        return in_array($this, [self::Credit, self::Release, self::Refund], true);
    }
}
