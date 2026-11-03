<?php

namespace App\Exceptions;

use App\Models\Wallet;
use RuntimeException;

class InsufficientWalletBalance extends RuntimeException
{
    /**
     * Create the exception for the given wallet shortfall.
     */
    public static function for(Wallet $wallet, float $amount): self
    {
        return new self(sprintf(
            'Wallet %d has %s available but %s is required.',
            $wallet->id,
            number_format($wallet->availableBalance(), 2),
            number_format($amount, 2),
        ));
    }
}
