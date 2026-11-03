<?php

namespace App\Exceptions;

use App\Enums\ClientStatus;
use App\Models\Client;
use RuntimeException;

class InvalidClientTransition extends RuntimeException
{
    /**
     * Create the exception for a transition the lifecycle does not allow.
     */
    public static function between(Client $client, ClientStatus $from, ClientStatus $to): self
    {
        return new self(sprintf(
            'Client %s cannot move from %s to %s.',
            $client->code,
            $from->label(),
            $to->label(),
        ));
    }
}
