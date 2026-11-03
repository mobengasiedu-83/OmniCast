<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\ClientRegistration;
use App\Models\User;

/**
 * Authorises the client application review queue.
 */
class ClientRegistrationPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isPlatformUser() && $actor->can(Permission::ApproveClients->value);
    }

    public function view(User $actor, ClientRegistration $registration): bool
    {
        return $this->viewAny($actor);
    }

    public function approve(User $actor, ClientRegistration $registration): bool
    {
        return $this->viewAny($actor) && $registration->isPending();
    }

    public function reject(User $actor, ClientRegistration $registration): bool
    {
        return $this->approve($actor, $registration);
    }
}
