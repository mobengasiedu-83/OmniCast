<?php

namespace App\Services\Email;

use App\Contracts\EmailGateway;
use Illuminate\Support\Facades\Log;

/**
 * Development email transport: writes the message to the log instead of hitting a mail server.
 *
 * The default driver, so a local checkout can run the email campaign flow end to
 * end without SMTP credentials. Swap it with EMAIL_BROADCAST_DRIVER=smtp or
 * generic_json.
 */
class LogEmailGateway implements EmailGateway
{
    /**
     * Submit an email and report whether the transport accepted it.
     */
    public function send(OutboundEmail $email): EmailDispatchResult
    {
        Log::channel('email')->info('Email accepted by the development transport.', [
            'reference' => $email->reference,
            'recipient' => $email->maskedTo(),
            'from' => $email->from,
            'subject' => $email->subject,
            'format' => $email->format->value,
            'body_length' => mb_strlen($email->body),
            'attachments' => count($email->attachments),
        ]);

        return EmailDispatchResult::accepted('log-'.$email->reference);
    }

    /**
     * The transport name written onto each message row.
     */
    public function name(): string
    {
        return 'log';
    }
}
