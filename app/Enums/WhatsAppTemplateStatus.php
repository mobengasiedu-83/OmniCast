<?php

namespace App\Enums;

enum WhatsAppTemplateStatus: string
{
    case Unknown = 'unknown';
    case Draft = 'draft';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
