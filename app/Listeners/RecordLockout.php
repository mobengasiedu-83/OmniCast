<?php

namespace App\Listeners;

use App\Enums\LoginEvent;
use App\Models\LoginAudit;
use Illuminate\Auth\Events\Lockout;

/**
 * Records a throttled sign-in attempt.
 */
class RecordLockout
{
    /**
     * Handle the event.
     */
    public function handle(Lockout $event): void
    {
        LoginAudit::record(LoginEvent::Lockout, request());
    }
}
