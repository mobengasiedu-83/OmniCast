<?php

namespace App\Services\Support;

use App\Enums\SupportTicketStatus;
use App\Mail\SupportAcknowledgementMail;
use App\Mail\SupportTeamAlertMail;
use App\Mail\SupportTicketUpdateMail;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends the three emails a support desk owes people.
 *
 * The requester hears that their message arrived and that it moved; the team
 * hears that a conversation is waiting. Each send is optional — an operator can
 * switch the acknowledgement, the status updates and the team alerts off in the
 * admin screen — and a failed send is logged rather than shown to the visitor,
 * because a mail server problem must not lose the enquiry they just wrote.
 */
class SupportNotifier
{
    public function __construct(protected SupportSettings $settings) {}

    /**
     * A conversation was opened: acknowledge it and alert the team.
     */
    public function conversationOpened(SupportTicket $ticket): void
    {
        if ($this->settings->acknowledgesEnquiries()) {
            $this->send($ticket->email, new SupportAcknowledgementMail($ticket));
        }

        $this->alertTeam(
            $ticket,
            $ticket->isEnquiry() ? 'A new enquiry was submitted' : 'A new support ticket was opened',
            null,
        );
    }

    /**
     * An agent answered: tell the requester, unless the message is an internal
     * note that was never meant for them.
     */
    public function agentReplied(SupportTicket $ticket, SupportTicketMessage $message): void
    {
        $this->send($ticket->email, new SupportTicketUpdateMail(
            $ticket,
            'A new reply from the support team',
            $message->body,
            $ticket->isTicket() ? route('support.tickets.show', $ticket->reference) : null,
        ));
    }

    /**
     * The requester answered: the team may already be watching the conversation,
     * but a fresh message means it needs them again.
     */
    public function requesterReplied(SupportTicket $ticket, SupportTicketMessage $message): void
    {
        $this->alertTeam($ticket, "{$ticket->name} replied", $message);
    }

    /**
     * The status moved, which is what the requester waits for.
     */
    public function statusChanged(SupportTicket $ticket, SupportTicketStatus $status): void
    {
        if (! $this->settings->notifiesOnStatusChange()) {
            return;
        }

        $this->send($ticket->email, new SupportTicketUpdateMail(
            $ticket,
            'Your conversation is now '.mb_strtolower($status->label()),
            $status->isSettled()
                ? 'No further action is needed from you. Reply to this message if the problem comes back and the conversation will reopen.'
                : 'The support team has picked your conversation back up.',
            $ticket->isTicket() ? route('support.tickets.show', $ticket->reference) : null,
        ));
    }

    /**
     * Alert the team that owns the conversation.
     */
    protected function alertTeam(SupportTicket $ticket, string $headline, ?SupportTicketMessage $message): void
    {
        if (! $this->settings->notifiesTeam()) {
            return;
        }

        $recipient = $this->teamAddress($ticket);

        if ($recipient === null) {
            return;
        }

        $this->send($recipient, new SupportTeamAlertMail(
            $ticket,
            $headline,
            $message,
            $ticket->isEnquiry()
                ? route('admin.support.enquiries.show', $ticket->reference)
                : route('admin.support.tickets.show', $ticket->reference),
        ));
    }

    /**
     * The team's own inbox, falling back to the platform support address.
     */
    protected function teamAddress(SupportTicket $ticket): ?string
    {
        $teamEmail = trim((string) $ticket->team?->email);

        return $teamEmail !== '' ? $teamEmail : $this->settings->supportEmail();
    }

    /**
     * Queue one message, logging rather than failing the request it belongs to.
     */
    protected function send(?string $recipient, object $mailable): void
    {
        $recipient = trim((string) $recipient);

        if ($recipient === '' || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            return;
        }

        try {
            Mail::to($recipient)->queue($mailable);
        } catch (Throwable $exception) {
            Log::warning('A support desk email could not be queued.', [
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
