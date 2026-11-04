<?php

namespace App\Exceptions;

use RuntimeException;

class CampaignSnapshotLocked extends RuntimeException
{
    /**
     * Create the exception for a frozen campaign version that cannot change.
     */
    public static function for(int $version): self
    {
        return new self(sprintf(
            'Campaign version %d is frozen and can no longer change or be deleted.',
            $version,
        ));
    }
}
