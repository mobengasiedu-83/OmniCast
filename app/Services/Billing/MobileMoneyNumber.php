<?php

namespace App\Services\Billing;

use App\Enums\MobileMoneyProvider;

/**
 * A Ghanaian mobile money number in the local form Paystack charges.
 *
 * Clients enter the wallet number the way they dial it (`0244215189`), as an
 * international number (`+233 24 421 5189`) or without the leading zero; the
 * gateway only ever sees `0XXXXXXXXX`.
 */
final class MobileMoneyNumber
{
    private function __construct(
        public readonly string $local,
        public readonly ?MobileMoneyProvider $provider,
    ) {}

    /**
     * Read any of the shapes a client may type into one normalised number.
     */
    public static function parse(?string $value): ?self
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        if (str_starts_with($digits, '233') && mb_strlen($digits) === 12) {
            $digits = '0'.mb_substr($digits, 3);
        } elseif (mb_strlen($digits) === 9) {
            $digits = '0'.$digits;
        }

        if (preg_match('/^0\d{9}$/', $digits) !== 1) {
            return null;
        }

        return new self($digits, MobileMoneyProvider::forNumber($digits));
    }
}
