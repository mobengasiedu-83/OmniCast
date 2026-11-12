<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WhatsAppProvider;

class WhatsAppProviderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('whatsapp.providers.view');
    }

    public function view(User $user, WhatsAppProvider $whatsAppProvider): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('whatsapp.providers.manage');
    }

    public function update(User $user, WhatsAppProvider $whatsAppProvider): bool
    {
        return $user->can('whatsapp.providers.manage');
    }

    public function test(User $user, WhatsAppProvider $whatsAppProvider): bool
    {
        return $user->can('whatsapp.providers.test');
    }
}
