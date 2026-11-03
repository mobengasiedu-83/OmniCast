<?php

namespace App\Listeners;

use App\Enums\LoginEvent;
use App\Models\LoginAudit;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;

/**
 * Records a completed password reset.
 */
class RecordPasswordReset
{
    /**
     * Handle the event.
     */
    public function handle(PasswordReset $event): void
    {
        LoginAudit::record(
            LoginEvent::PasswordReset,
            request(),
            $event->user instanceof User ? $event->user : null,
        );
    }
}
