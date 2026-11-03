<?php

namespace App\Services;

/**
 * Normalises Ghanaian phone numbers into the E.164 form used for delivery.
 */
class PhoneNumber
{
    /**
     * Normalise a phone number so the same recipient is never stored twice.
     */
    public function normalize(string $phone): string
    {
        $digits = preg_replace('/[^0-9+]/', '', $phone) ?? '';

        if (str_starts_with($digits, '+')) {
            return $digits;
        }

        if (str_starts_with($digits, '00')) {
            return '+'.substr($digits, 2);
        }

        if (str_starts_with($digits, '0')) {
            return '+233'.substr($digits, 1);
        }

        if (str_starts_with($digits, '233')) {
            return '+'.$digits;
        }

        return '+'.$digits;
    }

    /**
     * Whether the number looks like a dialable E.164 number.
     */
    public function isValid(string $phone): bool
    {
        return (bool) preg_match('/^\+[1-9]\d{7,14}$/', $this->normalize($phone));
    }
}
