<?php

namespace App\Listeners;

use App\Enums\LoginEvent;
use App\Models\LoginAudit;
use App\Models\User;
use Illuminate\Auth\Events\Failed;

/**
 * Records a rejected sign-in attempt.
 */
class RecordFailedLogin
{
    /**
     * Handle the event.
     */
    public function handle(Failed $event): void
    {
        $request = request();

        if ($request->attributes->get('login_audited') === true) {
            return;
        }

        $email = $event->credentials['email'] ?? null;

        LoginAudit::record(
            LoginEvent::LoginFailed,
            $request,
            $event->user instanceof User ? $event->user : null,
            is_string($email) ? $email : null,
            ['guard' => $event->guard],
        );
    }
}
