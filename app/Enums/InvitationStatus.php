<?php

namespace App\Enums;

/**
 * The lifecycle of a user invitation.
 */
enum InvitationStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Revoked = 'revoked';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Accepted => 'Accepted',
            self::Revoked => 'Revoked',
            self::Expired => 'Expired',
        };
    }

    /**
     * Whether the invitation can no longer be accepted.
     */
    public function isFinal(): bool
    {
        return $this !== self::Pending;
    }

    /**
     * The status values stored by the model.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $status): string => $status->value, self::cases());
    }
}
