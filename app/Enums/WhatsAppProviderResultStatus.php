<?php

namespace App\Enums;

enum WhatsAppProviderResultStatus: string
{
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Unknown = 'unknown';
}
