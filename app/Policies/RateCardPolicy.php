<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\RateCard;
use App\Models\User;

/**
 * Authorises access to the rate cards that decide what a campaign costs.
 */
class RateCardPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewRates->value);
    }

    public function view(User $user, RateCard $rateCard): bool
    {
        return $this->viewAny($user)
            && ($user->client_id === null || $user->client_id === $rateCard->client_id);
    }

    public function update(User $user, RateCard $rateCard): bool
    {
        return $user->can(Permission::ManageRates->value)
            && ($user->client_id === null || $user->client_id === $rateCard->client_id);
    }
}
