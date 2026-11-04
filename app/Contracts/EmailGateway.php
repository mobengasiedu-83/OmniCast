<?php

namespace App\Contracts;

use App\Services\Email\EmailDispatchResult;
use App\Services\Email\OutboundEmail;

/**
 * The outbound email transport used when an email campaign is dispatched.
 *
 * A transport only reports whether the mail server accepted the message;
 * mailbox delivery, bounces and complaints are confirmed later through the
 * signed email webhook.
 */
interface EmailGateway
{
    /**
     * Submit an email and report what the transport did with it.
     */
    public function send(OutboundEmail $email): EmailDispatchResult;

    /**
     * The transport name written onto each message row.
     */
    public function name(): string;
}
