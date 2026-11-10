<?php

namespace App\Enums;

/**
 * Where an announcement is in its life: written, waiting for a send time, being
 * fanned out, or done.
 */
enum AnnouncementStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Queued = 'queued';
    case Sent = 'sent';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Scheduled => 'Scheduled',
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
            self::Scheduled => 'info',
            self::Queued => 'warning',
            self::Sent => 'success',
        };
    }

    /**
     * Whether the announcement is waiting for a time rather than for a queue.
     */
    public function isScheduled(): bool
    {
        return $this === self::Scheduled;
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
