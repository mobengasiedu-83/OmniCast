<?php

namespace App\Enums;

enum PrivacyRequestAction: string
{
    case Access = 'access';
    case Erasure = 'erasure';

    public function label(): string
    {
        return match ($this) {
            self::Access => 'Data access export',
            self::Erasure => 'Erasure / anonymisation',
        };
    }
}
