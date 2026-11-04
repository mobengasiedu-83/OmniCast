<?php

namespace App\Services\Email;

use App\Contracts\EmailGateway;

/**
 * Transport used when email broadcasting is switched off.
 *
 * No mail server is contacted: every recipient is failed with the reason the
 * operator can act on, instead of the campaign silently reporting success.
 */
class DisabledEmailGateway implements EmailGateway
{
    public function __construct(protected string $reason) {}

    /**
     * Refuse the message without contacting a transport.
     */
    public function send(OutboundEmail $email): EmailDispatchResult
    {
        return EmailDispatchResult::rejected($this->reason);
    }

    /**
     * The transport name written onto each message row.
     */
    public function name(): string
    {
        return 'disabled';
    }
}
