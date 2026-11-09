<?php

namespace App\Enums;

/**
 * Where a policy version sits in its life.
 *
 * A document keeps exactly one published version at a time. A newer version is
 * prepared as a draft, so an administrator can write and review it without the
 * published text changing under the customers who already accepted it.
 */
enum LegalDocumentStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
            self::Archived => 'Archived',
        };
    }

    /**
     * The badge tone the admin screen uses for the status.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'muted',
            self::Published => 'success',
            self::Archived => 'warning',
        };
    }

    /**
     * Whether the version is the one customers read and accept.
     */
    public function isPublished(): bool
    {
        return $this === self::Published;
    }

    /**
     * The status values, as stored on a version.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $status): string => $status->value, self::cases());
    }
}
