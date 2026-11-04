<?php

namespace App\Enums;

/**
 * Why an address was excluded from broadcasts.
 */
enum ContactSuppressionReason: string
{
    case Dnd = 'dnd';
    case Bounce = 'bounce';
    case Complaint = 'complaint';
    case Manual = 'manual';

    /**
     * The human readable label shown in the suppression registry.
     */
    public function label(): string
    {
        return match ($this) {
            self::Dnd => 'Do not disturb (DND)',
            self::Bounce => 'Hard bounce',
            self::Complaint => 'Complaint',
            self::Manual => 'Manual',
        };
    }

    /**
     * The select options used by the suppression form.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $reason): array => ['value' => $reason->value, 'label' => $reason->label()],
            self::cases(),
        );
    }
}
