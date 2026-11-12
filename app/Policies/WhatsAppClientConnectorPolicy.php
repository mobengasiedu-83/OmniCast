<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WhatsAppClientConnector;

class WhatsAppClientConnectorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('whatsapp.connectors.view');
    }

    public function view(User $user, WhatsAppClientConnector $whatsAppClientConnector): bool
    {
        return $this->viewAny($user)
            && ($user->isPlatformUser()
                || (int) $user->client_id === (int) $whatsAppClientConnector->client_id);
    }

    public function create(User $user): bool
    {
        return $user->client_id !== null && $user->can('whatsapp.connectors.view');
    }

    public function update(User $user, WhatsAppClientConnector $whatsAppClientConnector): bool
    {
        return $this->create($user)
            && (int) $user->client_id === (int) $whatsAppClientConnector->client_id;
    }

    public function approve(User $user, WhatsAppClientConnector $whatsAppClientConnector): bool
    {
        return $user->can('whatsapp.connectors.approve')
            && $user->isPlatformUser();
    }
}
