<?php

namespace App\Mail;

use App\Enums\SenderIdStatus;
use App\Models\SenderId;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the person who requested a sender ID how compliance decided on it, so
 * they know whether they can start using it or have to correct the request.
 */
class SenderIdDecisionMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public SenderId $senderId,
        public SenderIdStatus $decision,
        public ?User $requester = null,
        public ?User $reviewer = null,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $channelLabel = strtolower($this->senderId->channel->label());

        return new Envelope(
            subject: $this->decision === SenderIdStatus::Approved
                ? "Your {$channelLabel} sender ID {$this->senderId->name} is approved"
                : "Your {$channelLabel} sender ID {$this->senderId->name} was not approved",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $this->senderId->loadMissing('client:id,name');

        return new Content(
            view: 'mail.sender-id-decision',
            with: [
                'approved' => $this->decision === SenderIdStatus::Approved,
                'recipientName' => $this->requester?->name,
                'senderName' => $this->senderId->name,
                'channelLabel' => $this->senderId->channel->label(),
                'identifierLabel' => $this->senderId->channel->identifierLabel(),
                'clientName' => $this->senderId->client?->name ?? 'your account',
                'country' => $this->senderId->country,
                'purpose' => $this->senderId->purpose,
                'reason' => $this->senderId->rejection_reason,
                'reviewerName' => $this->reviewer?->name,
                'decidedAt' => ($this->senderId->approved_at ?? $this->senderId->updated_at)?->toDayDateTimeString(),
                'actionUrl' => route('sender-ids.index'),
            ],
        );
    }
}
