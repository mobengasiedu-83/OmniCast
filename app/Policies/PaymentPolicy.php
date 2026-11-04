<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Payment;
use App\Models\User;

/**
 * Authorises a client, or a platform operator acting for it, to look at a
 * wallet top up and the receipt it produced.
 */
class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewWallet->value);
    }

    public function view(User $user, Payment $payment): bool
    {
        return $this->viewAny($user)
            && ($user->client_id === null || $user->client_id === $payment->client_id);
    }

    public function retry(User $user, Payment $payment): bool
    {
        return $user->can(Permission::TopUpWallet->value)
            && ($user->client_id === null || $user->client_id === $payment->client_id);
    }

    /**
     * Whether the user may ask the gateway what happened to an open payment.
     */
    public function verify(User $user, Payment $payment): bool
    {
        return $user->can(Permission::ManageWallet->value);
    }
}
