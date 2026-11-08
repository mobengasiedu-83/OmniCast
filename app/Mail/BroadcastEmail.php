<?php

namespace App\Mail;

use App\Services\Email\OutboundAttachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

/**
 * The campaign email handed to the mail transport.
 *
 * The body is authored by the client in the campaign wizard, so it is rendered
 * as-is inside the broadcast shell; an HTML broadcast always carries the
 * generated plain-text alternative the transport built. Attachments are read
 * from their disk when the message is submitted, never carried in the payload.
 */
class BroadcastEmail extends Mailable
{
    /**
     * Create a new message instance.
     *
     * @param  list<OutboundAttachment>  $files
     */
    public function __construct(
        public string $subjectLine,
        public ?string $htmlBody,
        public string $textBody,
        public string $reference,
        public array $files = [],
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

    /**
     * The files the campaign delivers with the message.
     *
     * A missing file is skipped rather than failing the send: one deleted
     * attachment must not stop the whole campaign.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $attachments = [];

        foreach ($this->files as $file) {
            if (! $file->exists()) {
                continue;
            }

            $attachments[] = Attachment::fromStorageDisk($file->disk, $file->path)
                ->as($file->name)
                ->withMime($file->mimeType);
        }

        return $attachments;
    }
}
