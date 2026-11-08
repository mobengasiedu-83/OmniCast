<?php

namespace App\Enums;

/**
 * The bind an ESME opened: what it may do on the session.
 */
enum SmppBindMode: string
{
    case Transmitter = 'transmitter';
    case Receiver = 'receiver';
    case Transceiver = 'transceiver';

    public function label(): string
    {
        return match ($this) {
            self::Transmitter => 'Transmitter',
            self::Receiver => 'Receiver',
            self::Transceiver => 'Transceiver',
        };
    }

    /**
     * Whether the session may submit messages (`submit_sm`).
     */
    public function allowsSubmit(): bool
    {
        return $this !== self::Receiver;
    }

    /**
     * Whether the session may receive receipts (`deliver_sm`).
     */
    public function allowsDeliver(): bool
    {
        return $this !== self::Transmitter;
    }

    /**
     * The bind modes an account may be restricted to.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $mode): string => $mode->value, self::cases());
    }
}
