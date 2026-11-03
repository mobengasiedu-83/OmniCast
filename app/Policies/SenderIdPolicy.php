<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\SenderId;
use App\Models\User;

/**
 * Authorises sender ID request actions for the client tenant the user belongs to.
 */
class SenderIdPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewSenderIds->value);
    }

    public function view(User $user, SenderId $senderId): bool
    {
        return $this->viewAny($user) && $this->sameClient($user, $senderId);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageSenderIds->value);
    }

    public function approve(User $user, SenderId $senderId): bool
    {
        return $user->can(Permission::ApproveSenderIds->value) && $this->sameClient($user, $senderId);
    }

    public function delete(User $user, SenderId $senderId): bool
    {
        return $user->can(Permission::ManageSenderIds->value)
            && $this->sameClient($user, $senderId)
            && ! $senderId->status->isApproved();
    }

    protected function sameClient(User $user, SenderId $senderId): bool
    {
        return $user->client_id === null || $user->client_id === $senderId->client_id;
    }
}
