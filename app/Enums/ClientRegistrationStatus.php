<?php

namespace App\Enums;

/**
 * The review state of a prospective client's application.
 */
enum ClientRegistrationStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Awaiting review',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Withdrawn => 'Withdrawn',
        };
    }

    /**
     * Whether the application is still waiting on a decision.
     */
    public function isDecidable(): bool
    {
        return $this === self::Pending;
    }
}
