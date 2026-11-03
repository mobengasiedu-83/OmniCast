<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Contact;
use App\Models\User;

/**
 * Authorises contact book actions for the client tenant the user belongs to.
 */
class ContactPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewContacts->value);
    }

    public function view(User $user, Contact $contact): bool
    {
        return $this->viewAny($user) && $this->sameClient($user, $contact);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageContacts->value);
    }

    public function import(User $user): bool
    {
        return $user->can(Permission::ImportContacts->value);
    }

    public function update(User $user, Contact $contact): bool
    {
        return $user->can(Permission::ManageContacts->value) && $this->sameClient($user, $contact);
    }

    public function delete(User $user, Contact $contact): bool
    {
        return $this->update($user, $contact);
    }

    protected function sameClient(User $user, Contact $contact): bool
    {
        return $user->client_id === null || $user->client_id === $contact->client_id;
    }
}
