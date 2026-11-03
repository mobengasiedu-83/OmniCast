<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

/**
 * Authorises the platform user directory.
 *
 * Only the platform team administers accounts across tenants; client users
 * manage their own colleagues through the team settings screen.
 */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isPlatformUser() && $actor->can(Permission::ManageUsers->value);
    }

    public function view(User $actor, User $target): bool
    {
        return $this->viewAny($actor);
    }

    public function create(User $actor): bool
    {
        return $this->viewAny($actor);
    }

    public function update(User $actor, User $target): bool
    {
        return $this->viewAny($actor) && ! $this->protectsSuperAdmin($actor, $target);
    }

    public function suspend(User $actor, User $target): bool
    {
        return $this->update($actor, $target) && $actor->id !== $target->id;
    }

    public function reactivate(User $actor, User $target): bool
    {
        return $this->update($actor, $target);
    }

    public function signOut(User $actor, User $target): bool
    {
        return $this->viewAny($actor) && ! $this->protectsSuperAdmin($actor, $target);
    }

    /**
     * Only a super admin may act on another super admin.
     */
    protected function protectsSuperAdmin(User $actor, User $target): bool
    {
        return $target->isSuperAdmin() && ! $actor->isSuperAdmin();
    }
}
