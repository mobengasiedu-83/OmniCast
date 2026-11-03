<?php

namespace App\Listeners;

use App\Enums\LoginEvent;
use App\Models\LoginAudit;
use App\Models\User;
use Illuminate\Auth\Events\Logout;

/**
 * Records a sign-out.
 */
class RecordLogout
{
    /**
     * Handle the event.
     */
    public function handle(Logout $event): void
    {
        LoginAudit::record(
            LoginEvent::Logout,
            request(),
            $event->user instanceof User ? $event->user : null,
        );
    }
}
