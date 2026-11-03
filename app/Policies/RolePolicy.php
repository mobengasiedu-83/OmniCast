<?php

namespace App\Policies;

use App\Enums\Role as RoleEnum;
use App\Models\User;
use Spatie\Permission\Models\Role;

/**
 * Authorises edits to the role permission matrix.
 */
class RolePolicy
{
    public function viewAny(User $actor): bool
    {
        return $this->managesRoles($actor);
    }

    public function update(User $actor, Role $role): bool
    {
        if (! $this->managesRoles($actor)) {
            return false;
        }

        // The super admin role always holds every permission.
        return $role->name !== RoleEnum::SuperAdmin->value;
    }

    /**
     * Role editing is restricted to the platform super admins.
     */
    protected function managesRoles(User $actor): bool
    {
        return $actor->isPlatformUser() && $actor->isSuperAdmin();
    }
}
