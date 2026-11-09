<?php

namespace App\Mail;

use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the owning team that a conversation needs them.
 *
 * The alert carries enough to triage without opening OmniCast: who wrote, what
 * it is about, what they said, and where to answer it.
 */
class SupportTeamAlertMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public SupportTicket $ticket,
        public string $headline,
        public ?SupportTicketMessage $message = null,
        public ?string $actionUrl = null,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[{$this->ticket->reference}] {$this->ticket->subject}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $this->ticket->loadMissing(['category:id,name', 'team:id,name', 'client:id,name']);

        return new Content(
            view: 'mail.support-team-alert',
            with: [
                'headline' => $this->headline,
                'reference' => $this->ticket->reference,
                'subjectLine' => $this->ticket->subject,
                'requesterName' => $this->ticket->name,
                'requesterEmail' => $this->ticket->email,
                'requesterPhone' => $this->ticket->phone,
                'company' => $this->ticket->company ?? $this->ticket->client?->name,
                'category' => $this->ticket->category?->name,
                'team' => $this->ticket->team?->name,
                'priority' => $this->ticket->priority->label(),
                'status' => $this->ticket->status->label(),
                'isTicket' => $this->ticket->isTicket(),
                'body' => $this->message?->body ?? $this->ticket->description,
                'actionUrl' => $this->actionUrl,
            ],
        );
    }
}
