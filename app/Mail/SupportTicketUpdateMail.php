<?php

namespace App\Mail;

use App\Models\SupportTicket;
use App\Services\Support\SupportSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the requester their conversation moved: an agent replied, or the status
 * changed. One mailable serves both because the requester reads one thing — the
 * headline, the message and a way back in.
 */
class SupportTicketUpdateMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public SupportTicket $ticket,
        public string $headline,
        public ?string $message = null,
        public ?string $actionUrl = null,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "{$this->ticket->reference} — {$this->headline}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mail.support-ticket-update',
            with: [
                'name' => $this->ticket->name,
                'reference' => $this->ticket->reference,
                'subjectLine' => $this->ticket->subject,
                'headline' => $this->headline,
                'message' => $this->message,
                'actionUrl' => $this->actionUrl,
                'supportEmail' => app(SupportSettings::class)->supportEmail(),
            ],
        );
    }
}
