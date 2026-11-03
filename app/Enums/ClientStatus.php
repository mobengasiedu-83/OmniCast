<?php

namespace App\Enums;

/**
 * The lifecycle of a client tenant.
 *
 * A tenant is created pending while its application is reviewed, becomes active
 * once onboarding is signed off, may be suspended with a reason at any point and
 * is finally archived when the relationship ends.
 */
enum ClientStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending activation',
            self::Active => 'Active',
            self::Suspended => 'Suspended',
            self::Archived => 'Archived',
        };
    }

    /**
     * Whether a tenant in this state may still broadcast.
     */
    public function canBroadcast(): bool
    {
        return $this === self::Active;
    }

    /**
     * The states this one may move to.
     *
     * Archived is terminal: an archived tenant is re-onboarded rather than
     * reactivated, otherwise its history would silently reopen.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Active, self::Archived],
            self::Active => [self::Suspended, self::Archived],
            self::Suspended => [self::Active, self::Archived],
            self::Archived => [],
        };
    }

    /**
     * Whether the tenant may move to the given state.
     */
    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * Whether the tenant's users are signed out on their next request.
     */
    public function blocksSignIn(): bool
    {
        return in_array($this, [self::Suspended, self::Archived], true);
    }

    /**
     * The status values, as stored in the database.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $status): string => $status->value, self::cases());
    }
}
