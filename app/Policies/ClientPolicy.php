<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Client;
use App\Models\User;

/**
 * Authorises the client tenant directory and its lifecycle.
 *
 * The platform team administers tenants; a client user may only ever read the
 * tenant they belong to.
 */
class ClientPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isPlatformUser() && $actor->can(Permission::ManageClients->value);
    }

    public function view(User $actor, Client $client): bool
    {
        return $this->viewAny($actor) || $client->id === $actor->client_id;
    }

    public function update(User $actor, Client $client): bool
    {
        return $this->viewAny($actor);
    }

    public function transition(User $actor, Client $client): bool
    {
        return $this->viewAny($actor);
    }

    public function approve(User $actor, Client $client): bool
    {
        return $actor->isPlatformUser() && $actor->can(Permission::ApproveClients->value);
    }
}
