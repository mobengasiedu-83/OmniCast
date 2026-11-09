<?php

namespace App\Enums;

/**
 * Where an organisation stands on its Data Processing Agreement.
 *
 * The DPA is signed per organisation rather than per user, so the sign-off is
 * tracked on the tenant and every member of the account inherits the status.
 */
enum OrganisationDpaStatus: string
{
    case NotStarted = 'not-started';
    case Requested = 'requested';
    case AwaitingSignature = 'awaiting-signature';
    case Signed = 'signed';
    case Declined = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::NotStarted => 'Not started',
            self::Requested => 'Requested',
            self::AwaitingSignature => 'Awaiting signature',
            self::Signed => 'Signed',
            self::Declined => 'Declined',
        };
    }

    /**
     * The badge tone the screens use for the status.
     */
    public function tone(): string
    {
        return match ($this) {
            self::NotStarted => 'muted',
            self::Requested => 'info',
            self::AwaitingSignature => 'warning',
            self::Signed => 'success',
            self::Declined => 'danger',
        };
    }

    /**
     * Whether the agreement is in force.
     */
    public function isSigned(): bool
    {
        return $this === self::Signed;
    }

    /**
     * The statuses, in the order the screens list them.
     *
     * @return list<array{value: string, label: string, tone: string}>
     */
    public static function catalogue(): array
    {
        return array_map(fn (self $status): array => [
            'value' => $status->value,
            'label' => $status->label(),
            'tone' => $status->tone(),
        ], self::cases());
    }

    /**
     * The status values, as stored on the organisation record.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $status): string => $status->value, self::cases());
    }
}
