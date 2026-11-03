<?php

namespace App\Enums;

enum ReservationStatus: string
{
    case Held = 'held';
    case Captured = 'captured';
    case Released = 'released';

    public function label(): string
    {
        return match ($this) {
            self::Held => 'Held',
            self::Captured => 'Captured',
            self::Released => 'Released',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::Held;
    }
}
