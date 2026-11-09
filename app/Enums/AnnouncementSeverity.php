<?php

namespace App\Enums;

/**
 * How loudly a notification or an announcement presents itself.
 *
 * Severity is presentation only: the category decides whether a user receives
 * the message at all.
 */
enum AnnouncementSeverity: string
{
    case Info = 'info';
    case Success = 'success';
    case Warning = 'warning';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Info => 'Information',
            self::Success => 'Good news',
            self::Warning => 'Warning',
            self::Critical => 'Critical',
        };
    }

    /**
     * The badge tone the screens use for the severity.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Info => 'info',
            self::Success => 'success',
            self::Warning => 'warning',
            self::Critical => 'destructive',
        };
    }

    /**
     * The severity values, as stored on an announcement.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $severity): string => $severity->value, self::cases());
    }
}
