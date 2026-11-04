<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Invoice;
use App\Models\User;

/**
 * Authorises access to a wallet funding invoice.
 */
class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewWallet->value);
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $this->viewAny($user)
            && ($user->client_id === null || $user->client_id === $invoice->client_id);
    }
}
