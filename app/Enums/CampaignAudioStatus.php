<?php

namespace App\Enums;

/**
 * The review state of the audio (recording or script) a voice campaign dials out with.
 *
 * Voice campaigns share one platform DID, so the spoken content is the only
 * per-campaign asset a reviewer has to approve before delivery may start.
 */
enum CampaignAudioStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending review',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }

    public function isApproved(): bool
    {
        return $this === self::Approved;
    }

    public function canBeReviewed(): bool
    {
        return in_array($this, [self::Pending, self::Rejected], true);
    }
}
