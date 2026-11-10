<?php

namespace App\Services\Notifications;

use Illuminate\Notifications\DatabaseNotification;

/**
 * What one notification actually did.
 *
 * An announcement has to record whether its recipient also received the email
 * copy, and a caller that only gets the inbox row back cannot tell the
 * difference between "category muted" and "email only".
 */
final class NotificationDelivery
{
    public function __construct(
        public readonly ?DatabaseNotification $notification,
        public readonly bool $emailed,
        public readonly bool $texted = false,
    ) {}

    /**
     * Whether anything at all reached the account.
     */
    public function sent(): bool
    {
        return $this->notification !== null || $this->emailed || $this->texted;
    }

    /**
     * The inbox row the announcement should point at, if one was written.
     */
    public function id(): ?string
    {
        return $this->notification?->id;
    }
}
