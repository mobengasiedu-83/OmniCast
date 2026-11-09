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
 * Confirms to the requester that their message arrived, and gives them the
 * reference to quote when they follow up.
 */
class SupportAcknowledgementMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(public SupportTicket $ticket) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "We have received {$this->ticket->reference}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $settings = app(SupportSettings::class);
        $this->ticket->loadMissing('category:id,name');

        return new Content(
            view: 'mail.support-acknowledgement',
            with: [
                'name' => $this->ticket->name,
                'reference' => $this->ticket->reference,
                'subjectLine' => $this->ticket->subject,
                'category' => $this->ticket->category?->name,
                'team' => $this->ticket->team?->name,
                'isTicket' => $this->ticket->isTicket(),
                'responseTime' => $settings->responseTime(),
                'supportEmail' => $settings->supportEmail(),
                'ticketUrl' => $this->ticket->isTicket() ? route('support.tickets.show', $this->ticket->reference) : null,
            ],
        );
    }
}
