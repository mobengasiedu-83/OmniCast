<?php

namespace App\Enums;

/**
 * The lifecycle of a message template.
 *
 * A client writes a template as a draft, publishes it for the tenant's own use
 * or submits it to the platform for review, and the platform may archive any
 * template it no longer wants offered in the library.
 */
enum TemplateStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Active = 'active';
    case Rejected = 'rejected';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Pending => 'Awaiting review',
            self::Active => 'Active',
            self::Rejected => 'Rejected',
            self::Archived => 'Archived',
        };
    }

    /**
     * The statuses the author may still rewrite the copy of a template in.
     */
    public function canBeEdited(): bool
    {
        return in_array($this, [self::Draft, self::Pending, self::Active, self::Rejected], true);
    }

    /**
     * Whether the template may be picked in the campaign editor.
     *
     * Drafts stay available to their owner so a half-finished starting point is
     * never a dead end, while archived copy is hidden from everybody.
     */
    public function isUsable(): bool
    {
        return in_array($this, [self::Draft, self::Active], true);
    }

    /**
     * Whether the platform reviewer still has a decision to take.
     */
    public function canBeReviewed(): bool
    {
        return $this === self::Pending;
    }
}
