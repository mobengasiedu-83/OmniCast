<?php

namespace App\Enums;

/**
 * The authentication events recorded in the login audit trail.
 */
enum LoginEvent: string
{
    case LoginSucceeded = 'login.succeeded';
    case LoginFailed = 'login.failed';
    case LoginBlocked = 'login.blocked';
    case Logout = 'logout';
    case Lockout = 'lockout';
    case PasswordReset = 'password.reset';

    public function label(): string
    {
        return match ($this) {
            self::LoginSucceeded => 'Signed in',
            self::LoginFailed => 'Failed sign-in',
            self::LoginBlocked => 'Blocked sign-in',
            self::Logout => 'Signed out',
            self::Lockout => 'Rate limited',
            self::PasswordReset => 'Password reset',
        };
    }

    /**
     * The status tone the audit trail uses to badge the event.
     */
    public function tone(): string
    {
        return match ($this) {
            self::LoginSucceeded => 'active',
            self::LoginFailed, self::LoginBlocked => 'failed',
            self::Lockout => 'pending',
            self::PasswordReset => 'queued',
            self::Logout => 'draft',
        };
    }

    /**
     * Whether the event represents a rejected or unsuccessful attempt.
     */
    public function isFailure(): bool
    {
        return in_array($this, [self::LoginFailed, self::LoginBlocked, self::Lockout], true);
    }

    /**
     * The event values stored in the audit table.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $event): string => $event->value, self::cases());
    }
}
