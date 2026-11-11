<?php

namespace App\Enums;

enum PrivacySubjectType: string
{
    case Account = 'account';
    case Contact = 'contact';

    public function label(): string
    {
        return match ($this) {
            self::Account => 'Client account',
            self::Contact => 'Individual contact',
        };
    }
}
