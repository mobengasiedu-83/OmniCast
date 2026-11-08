<?php

namespace App\Services\Email;

use App\Contracts\EmailGateway;
use App\Mail\BroadcastEmail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Live email transport: hands each message to a Laravel mailer.
 *
 * The mailer is the `broadcast` entry in config/mail.php (MAIL_BROADCAST_*), so
 * the SMTP host, port, credentials and TLS setting stay deployment
 * configuration instead of code. A transport error is reported as a rejection
 * the campaign can retry rather than an exception that stops the dispatch.
 */
class SmtpEmailGateway implements EmailGateway
{
    public function __construct(protected ?string $mailer = null) {}

    /**
     * Submit an email through the configured mailer.
     */
    public function send(OutboundEmail $email): EmailDispatchResult
    {
        try {
            $mailable = (new BroadcastEmail(
                subjectLine: $email->subject,
                htmlBody: $email->htmlBody(),
                textBody: $email->textBody(),
                reference: $email->reference,
                files: $email->deliverableAttachments(),
            ))->from($email->from, $email->fromName);

            Mail::mailer($this->mailerName())->to($email->to)->send($mailable);
        } catch (Throwable $exception) {
            Log::channel('email')->warning('Email was rejected by the mail server.', [
                'reference' => $email->reference,
                'recipient' => $email->maskedTo(),
                'reason' => $exception->getMessage(),
            ]);

            return EmailDispatchResult::rejected('The mail server rejected the message: '.$exception->getMessage());
        }

        return EmailDispatchResult::accepted($this->messageId($email));
    }

    /**
     * The transport name written onto each message row.
     */
    public function name(): string
    {
        return 'smtp';
    }

    /**
     * The mailer this transport submits through.
     */
    protected function mailerName(): string
    {
        $mailer = $this->mailer ?? (string) config('email.mailer', 'broadcast');

        return $mailer === '' ? 'broadcast' : $mailer;
    }

    /**
     * The Message-ID handed to the mail server, so a bounce can be matched back.
     */
    protected function messageId(OutboundEmail $email): string
    {
        $domain = mb_substr((string) strrchr($email->from, '@') ?: '@omnicast.gh', 1);

        return $email->reference.'@'.($domain === '' ? 'omnicast.gh' : $domain);
    }
}
