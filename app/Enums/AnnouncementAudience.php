<?php

namespace App\Enums;

/**
 * Who an announcement is delivered to.
 *
 * The audience is resolved to a user query when the announcement is sent, not
 * when it is composed, so a draft sent tomorrow reaches the accounts that exist
 * tomorrow.
 */
enum AnnouncementAudience: string
{
    case Everyone = 'everyone';
    case Platform = 'platform';
    case Clients = 'clients';
    case Client = 'client';
    case Role = 'role';

    public function label(): string
    {
        return match ($this) {
            self::Everyone => 'Everyone',
            self::Platform => 'The platform team',
            self::Clients => 'Every client account',
            self::Client => 'One client account',
            self::Role => 'One role',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Everyone => 'Every active account on the platform, staff and clients.',
            self::Platform => 'Super admins, compliance and finance staff only.',
            self::Clients => 'Every active user of every active client tenant.',
            self::Client => 'Every active user of the chosen client tenant.',
            self::Role => 'Every active account holding the chosen role.',
        };
    }

    /**
     * Whether the audience names a single tenant.
     */
    public function requiresClient(): bool
    {
        return $this === self::Client;
    }

    /**
     * Whether the audience names a role.
     */
    public function requiresRole(): bool
    {
        return $this === self::Role;
    }

    /**
     * The audiences, as the composer lists them.
     *
     * @return list<array{value: string, label: string, description: string}>
     */
    public static function catalogue(): array
    {
        return array_map(fn (self $audience): array => [
            'value' => $audience->value,
            'label' => $audience->label(),
            'description' => $audience->description(),
        ], self::cases());
    }

    /**
     * The audience values, as stored on an announcement.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $audience): string => $audience->value, self::cases());
    }
}
