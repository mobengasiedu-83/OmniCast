<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;
use App\Models\UserInvitation;

/**
 * Authorises invitations to the platform team and to client tenants.
 */
class UserInvitationPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isPlatformUser() && $actor->can(Permission::ManageUsers->value);
    }

    public function create(User $actor): bool
    {
        return $actor->can(Permission::ManageUsers->value);
    }

    public function resend(User $actor, UserInvitation $invitation): bool
    {
        return $this->revoke($actor, $invitation);
    }

    public function revoke(User $actor, UserInvitation $invitation): bool
    {
        return $actor->can(Permission::ManageUsers->value)
            && $this->sameTenant($actor, $invitation);
    }

    /**
     * Platform users reach every tenant; client users only their own.
     */
    protected function sameTenant(User $actor, UserInvitation $invitation): bool
    {
        return $actor->isPlatformUser() || $actor->client_id === $invitation->client_id;
    }
}
