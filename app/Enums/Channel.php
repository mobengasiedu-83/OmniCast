<?php

namespace App\Enums;

/**
 * The delivery channels an OmniCast campaign can broadcast over.
 */
enum Channel: string
{
    case Voice = 'voice';
    case Sms = 'sms';
    case MultiChannel = 'multi_channel';

    public function label(): string
    {
        return match ($this) {
            self::Voice => 'Voice',
            self::Sms => 'SMS',
            self::MultiChannel => 'Multi-Channel',
        };
    }

    /**
     * The concrete channels a campaign of this type is dispatched over.
     *
     * @return list<self>
     */
    public function deliveryChannels(): array
    {
        return match ($this) {
            self::Voice => [self::Voice],
            self::Sms => [self::Sms],
            self::MultiChannel => [self::Voice, self::Sms],
        };
    }

    public function requiresSmsContent(): bool
    {
        return $this !== self::Voice;
    }

    public function requiresVoiceContent(): bool
    {
        return $this !== self::Sms;
    }

    public function isMultiChannel(): bool
    {
        return $this === self::MultiChannel;
    }
}
