<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\SupportTicket;
use App\Models\User;

/**
 * Who may read and answer a support conversation.
 *
 * A platform user reads the register when their role holds the support
 * permission. A client user reads only their own organisation's conversations,
 * and only a ticket — an enquiry came from a stranger and stays internal.
 */
class SupportTicketPolicy
{
    /**
     * Whether the user may open the register at all.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewSupport->value) || $user->client_id !== null;
    }

    /**
     * Whether the user may read this conversation.
     */
    public function view(User $user, SupportTicket $ticket): bool
    {
        if ($user->isPlatformUser()) {
            return $user->can(Permission::ViewSupport->value);
        }

        return $this->belongsToRequester($user, $ticket);
    }

    /**
     * Whether the user may add a message to the thread.
     */
    public function reply(User $user, SupportTicket $ticket): bool
    {
        if ($user->isPlatformUser()) {
            return $user->can(Permission::ManageSupport->value);
        }

        return $ticket->isTicket() && $this->belongsToRequester($user, $ticket);
    }

    /**
     * Whether the user may change the owner, priority, team or status.
     */
    public function update(User $user, SupportTicket $ticket): bool
    {
        return $user->isPlatformUser() && $user->can(Permission::ManageSupport->value);
    }

    /**
     * Whether the user may settle the conversation.
     */
    public function close(User $user, SupportTicket $ticket): bool
    {
        if ($user->isPlatformUser()) {
            return $user->can(Permission::ManageSupport->value);
        }

        return $ticket->isTicket() && $this->belongsToRequester($user, $ticket);
    }

    /**
     * Whether the conversation belongs to the user's own organisation.
     */
    protected function belongsToRequester(User $user, SupportTicket $ticket): bool
    {
        return $user->client_id !== null && $ticket->client_id === $user->client_id;
    }
}
