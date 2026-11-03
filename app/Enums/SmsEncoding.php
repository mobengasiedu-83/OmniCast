<?php

namespace App\Enums;

/**
 * The character encoding an SMS body is billed on.
 */
enum SmsEncoding: string
{
    case Gsm7 = 'gsm7';
    case Unicode = 'unicode';

    public function label(): string
    {
        return match ($this) {
            self::Gsm7 => 'GSM-7',
            self::Unicode => 'Unicode',
        };
    }

    public function singlePartLimit(): int
    {
        return $this === self::Gsm7 ? 160 : 70;
    }

    public function multiPartLimit(): int
    {
        return $this === self::Gsm7 ? 153 : 67;
    }
}
