<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;
use App\Models\Wallet;

/**
 * Authorises access to a client wallet and its ledger.
 */
class WalletPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewWallet->value);
    }

    public function view(User $user, Wallet $wallet): bool
    {
        return $this->viewAny($user)
            && ($user->client_id === null || $user->client_id === $wallet->client_id);
    }

    public function topUp(User $user, Wallet $wallet): bool
    {
        return $user->can(Permission::TopUpWallet->value)
            && ($user->client_id === null || $user->client_id === $wallet->client_id);
    }
}
