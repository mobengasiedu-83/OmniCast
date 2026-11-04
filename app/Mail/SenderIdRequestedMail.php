<?php

namespace App\Mail;

use App\Models\SenderId;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the platform compliance team that a client requested a sender ID.
 */
class SenderIdRequestedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public SenderId $senderId,
        public ?User $requester = null,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "{$this->senderId->channel->label()} sender ID request: {$this->senderId->name}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $this->senderId->loadMissing('client:id,name');

        return new Content(
            view: 'mail.sender-id-requested',
            with: [
                'senderName' => $this->senderId->name,
                'channelLabel' => $this->senderId->channel->label(),
                'identifierLabel' => $this->senderId->channel->identifierLabel(),
                'country' => $this->senderId->country,
                'purpose' => $this->senderId->purpose,
                'clientName' => $this->senderId->client?->name ?? 'Platform account',
                'requesterName' => $this->requester?->name,
                'requestedAt' => $this->senderId->requested_at?->toDayDateTimeString(),
                'reviewUrl' => route('sender-ids.index'),
            ],
        );
    }
}
