<?php

namespace App\Enums;

enum WhatsAppApprovalStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
