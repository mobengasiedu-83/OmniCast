<?php

namespace App\Enums;

/**
 * How much of the queue a conversation is allowed to jump.
 */
enum SupportPriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Low',
            self::Normal => 'Normal',
            self::High => 'High',
            self::Urgent => 'Urgent',
        };
    }

    /**
     * The ranking the queue sorts on, highest first.
     */
    public function weight(): int
    {
        return match ($this) {
            self::Low => 1,
            self::Normal => 2,
            self::High => 3,
            self::Urgent => 4,
        };
    }

    /**
     * The priority and its label, for a select control.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function catalogue(): array
    {
        return array_map(fn (self $priority): array => [
            'value' => $priority->value,
            'label' => $priority->label(),
        ], self::cases());
    }

    /**
     * The priority values, as they are stored.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $priority): string => $priority->value, self::cases());
    }
}
