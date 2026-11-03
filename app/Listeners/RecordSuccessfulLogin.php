<?php

namespace App\Listeners;

use App\Enums\LoginEvent;
use App\Models\LoginAudit;
use App\Models\User;
use Illuminate\Auth\Events\Login;

/**
 * Records a successful sign-in and stamps the user's last login.
 */
class RecordSuccessfulLogin
{
    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $request = request();

        LoginAudit::record(LoginEvent::LoginSucceeded, $request, $event->user);

        $event->user->recordLogin((string) $request->ip());
    }
}
