<?php

namespace App\Enums;

/**
 * The delivery channels an OmniCast campaign can broadcast over.
 */
enum Channel: string
{
    case Voice = 'voice';
    case Sms = 'sms';
    case Email = 'email';
    case WhatsApp = 'whatsapp';
    case MultiChannel = 'multi_channel';

    public function label(): string
    {
        return match ($this) {
            self::Voice => 'Voice',
            self::Sms => 'SMS',
            self::Email => 'Email',
            self::WhatsApp => 'WhatsApp',
            self::MultiChannel => 'Multi-Channel',
        };
    }

    /**
     * The concrete channels a campaign of this type is dispatched over.
     *
     * A multi-channel campaign pairs a voice call with an SMS; email is a
     * channel of its own, with its own audience and its own content.
     *
     * @return list<self>
     */
    public function deliveryChannels(): array
    {
        return match ($this) {
            self::Voice => [self::Voice],
            self::Sms => [self::Sms],
            self::Email => [self::Email],
            self::WhatsApp => [self::WhatsApp],
            self::MultiChannel => [self::Voice, self::Sms],
        };
    }

    public function requiresSmsContent(): bool
    {
        return in_array($this, [self::Sms, self::MultiChannel], true);
    }

    public function requiresVoiceContent(): bool
    {
        return in_array($this, [self::Voice, self::MultiChannel], true);
    }

    public function requiresEmailContent(): bool
    {
        return $this === self::Email;
    }

    public function requiresWhatsAppContent(): bool
    {
        return $this === self::WhatsApp;
    }

    public function isMultiChannel(): bool
    {
        return $this === self::MultiChannel;
    }
}
