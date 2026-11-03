<?php

namespace App\Enums;

enum ContactConsentStatus: string
{
    case Unknown = 'unknown';
    case OptedIn = 'opted_in';
    case OptedOut = 'opted_out';

    public function label(): string
    {
        return match ($this) {
            self::Unknown => 'Unknown',
            self::OptedIn => 'Opted In',
            self::OptedOut => 'Opted Out',
        };
    }

    public function allowsBroadcast(): bool
    {
        return $this !== self::OptedOut;
    }
}
