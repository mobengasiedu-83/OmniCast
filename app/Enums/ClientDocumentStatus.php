<?php

namespace App\Enums;

/**
 * The compliance review state of an uploaded client document.
 */
enum ClientDocumentStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Awaiting review',
            self::Verified => 'Verified',
            self::Rejected => 'Rejected',
        };
    }

    /**
     * Whether the document has been accepted by a reviewer.
     */
    public function isVerified(): bool
    {
        return $this === self::Verified;
    }
}
