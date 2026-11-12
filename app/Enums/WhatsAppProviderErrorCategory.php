<?php

namespace App\Enums;

enum WhatsAppProviderErrorCategory: string
{
    case Authentication = 'authentication';
    case Validation = 'validation';
    case RateLimited = 'rate_limited';
    case Provider = 'provider';
    case Transport = 'transport';
    case Unknown = 'unknown';
}
