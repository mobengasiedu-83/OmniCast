<?php

namespace App\Enums;

/**
 * Where an announcement is in its life: written, being fanned out, or done.
 */
enum AnnouncementStatus: string
{
    case Draft = 'draft';
    case Queued = 'queued';
    case Sent = 'sent';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Queued => 'Sending',
            self::Sent => 'Sent',
        };
    }

    /**
     * The badge tone the admin screen uses for the status.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'muted',
            self::Queued => 'warning',
            self::Sent => 'success',
        };
    }

    /**
     * Whether the announcement has already been delivered.
     */
    public function isSent(): bool
    {
        return $this === self::Sent;
    }

    /**
     * The status values, as stored on an announcement.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $status): string => $status->value, self::cases());
    }
}
