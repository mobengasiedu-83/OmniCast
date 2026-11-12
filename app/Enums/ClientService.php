<?php

namespace App\Enums;

/**
 * The billable services a client tenant can be entitled to.
 *
 * Entitlements are recorded per service rather than per campaign channel so a
 * tenant can be sold SMS without voice, or email without either.
 */
enum ClientService: string
{
    case Sms = 'sms';
    case Voice = 'voice';
    case Email = 'email';
    case WhatsApp = 'whatsapp';

    public function label(): string
    {
        return match ($this) {
            self::Sms => 'SMS',
            self::Voice => 'Voice',
            self::Email => 'Email',
            self::WhatsApp => 'WhatsApp',
        };
    }

    /**
     * The service that delivers the given campaign channel, if any.
     */
    public static function fromChannel(Channel $channel): ?self
    {
        return match ($channel) {
            Channel::Sms => self::Sms,
            Channel::Voice => self::Voice,
            Channel::Email => self::Email,
            Channel::MultiChannel => null,
        };
    }

    /**
     * The service values, as stored in the database.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $service): string => $service->value, self::cases());
    }
}
