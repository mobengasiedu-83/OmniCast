<?php

namespace App\Enums;

enum WhatsAppProviderStatus: string
{
    case Unconfigured = 'unconfigured';
    case Configured = 'configured';
    case Connected = 'connected';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Unconfigured => 'Unconfigured',
            self::Configured => 'Configured',
            self::Connected => 'Connected',
            self::Failed => 'Connection failed',
        };
    }
}
