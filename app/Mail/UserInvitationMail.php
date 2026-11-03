<?php

namespace App\Mail;

use App\Models\UserInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The invitation email carrying the one-time acceptance link.
 */
class UserInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public UserInvitation $invitation,
        public string $token,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'You have been invited to '.(string) config('app.name'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mail.user-invitation',
            with: [
                'inviteeName' => $this->invitation->name,
                'email' => $this->invitation->email,
                'roleLabel' => $this->invitation->roleLabel(),
                'organisation' => $this->invitation->client?->name,
                'inviterName' => $this->invitation->inviter?->name,
                'expiresAt' => $this->invitation->expires_at?->toFormattedDayDateString(),
                'acceptUrl' => route('invitations.accept.show', ['token' => $this->token]),
            ],
        );
    }
}
