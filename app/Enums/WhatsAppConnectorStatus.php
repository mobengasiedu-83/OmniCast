<?php

namespace App\Enums;

enum WhatsAppConnectorStatus: string
{
    case Available = 'available';
    case Disabled = 'disabled';
    case Unavailable = 'unavailable';
}
