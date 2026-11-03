<?php

namespace App\Enums;

enum SenderIdStatus: string
{
    case Pending = 'pending';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Suspended = 'suspended';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Submitted => 'Submitted',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Suspended => 'Suspended',
            self::Expired => 'Expired',
        };
    }

    public function isApproved(): bool
    {
        return $this === self::Approved;
    }

    public function canBeReviewed(): bool
    {
        return in_array($this, [self::Pending, self::Submitted], true);
    }
}
