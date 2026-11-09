<?php

namespace App\Enums;

/**
 * What a notification is about.
 *
 * The category is what a user switches on or off on `settings/notifications`
 * and what the messaging centre groups an inbox by, so a new event belongs to
 * exactly one of these rather than getting a category of its own.
 */
enum NotificationCategory: string
{
    case Campaigns = 'campaigns';
    case Automations = 'automations';
    case Billing = 'billing';
    case Compliance = 'compliance';
    case System = 'system';
    case Platform = 'platform';

    public function label(): string
    {
        return match ($this) {
            self::Campaigns => 'Campaigns',
            self::Automations => 'Automations',
            self::Billing => 'Billing & wallet',
            self::Compliance => 'Compliance & onboarding',
            self::System => 'System & security',
            self::Platform => 'Platform announcements',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Campaigns => 'A campaign finished, failed or needs attention.',
            self::Automations => 'A workflow run failed or a workflow was switched off.',
            self::Billing => 'Payments received, invoices raised and wallet balance warnings.',
            self::Compliance => 'A tenant applied, a document was reviewed or an account was suspended.',
            self::System => 'Queues, webhooks, SMPP sessions and other platform plumbing.',
            self::Platform => 'Messages the platform team sends to your account.',
        };
    }

    /**
     * The badge tone the screens use for this category.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Campaigns => 'info',
            self::Automations => 'secondary',
            self::Billing => 'success',
            self::Compliance => 'warning',
            self::System => 'muted',
            self::Platform => 'default',
        };
    }

    /**
     * The categories, in the order the screens list them.
     *
     * @return list<array{value: string, label: string, description: string, tone: string}>
     */
    public static function catalogue(): array
    {
        return array_map(fn (self $category): array => [
            'value' => $category->value,
            'label' => $category->label(),
            'description' => $category->description(),
            'tone' => $category->tone(),
        ], self::cases());
    }

    /**
     * The category values, as stored on a notification preference.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $category): string => $category->value, self::cases());
    }
}
