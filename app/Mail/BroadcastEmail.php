<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

/**
 * The campaign email handed to the mail transport.
 *
 * The body is authored by the client in the campaign wizard, so it is rendered
 * as-is inside the broadcast shell; an HTML broadcast always carries the
 * generated plain-text alternative the transport built.
 */
class BroadcastEmail extends Mailable
{
    /**
     * Create a new message instance.
     */
    public function __construct(
        public string $subjectLine,
        public ?string $htmlBody,
        public string $textBody,
        public string $reference,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: $this->htmlBody === null ? null : 'mail.broadcast',
            text: 'mail.broadcast-text',
            with: [
                'body' => $this->htmlBody ?? $this->textBody,
                'reference' => $this->reference,
            ],
        );
    }

    /**
     * Tag the message so a bounce report can echo the OmniCast reference back.
     */
    public function headers(): Headers
    {
        return new Headers(
            text: [
                'X-Omnicast-Reference' => $this->reference,
            ],
        );
    }
}
