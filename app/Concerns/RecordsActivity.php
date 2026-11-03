<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Writes privileged identity actions to the activity log.
 */
trait RecordsActivity
{
    /**
     * Record an action against the signed-in administrator.
     *
     * @param  array<string, mixed>  $properties
     */
    protected function recordActivity(string $description, ?Model $subject = null, array $properties = []): void
    {
        $actor = request()->user();

        $logger = activity()
            ->causedBy($actor instanceof Model ? $actor : null)
            ->withProperties($properties);

        if ($subject !== null) {
            $logger->performedOn($subject);
        }

        $logger->log($description);
    }
}
