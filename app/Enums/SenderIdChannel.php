<?php

namespace App\Enums;

/**
 * The delivery channel a sender identity is requested for.
 *
 * SMS sender IDs are the brand strings shown on the recipient handset, so their
 * case, spaces and symbols are part of the identity; email senders are the from
 * address broadcasts are delivered as, and voice senders are the caller numbers
 * recipients see on their handset.
 */
enum SenderIdChannel: string
{
    case Sms = 'sms';
    case Email = 'email';
    case Voice = 'voice';

    public function label(): string
    {
        return match ($this) {
            self::Sms => 'SMS',
            self::Email => 'Email',
            self::Voice => 'Voice',
        };
    }

    /**
     * The wording used for the identifier field of this channel.
     */
    public function identifierLabel(): string
    {
        return match ($this) {
            self::Sms => 'Sender ID',
            self::Email => 'From address',
            self::Voice => 'Caller number',
        };
    }

    public function placeholder(): string
    {
        return match ($this) {
            self::Sms => 'SysUp360',
            self::Email => 'noreply@yourdomain.com',
            self::Voice => '+233240000000',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Sms => sprintf(
                '%d–%d characters including spaces. Letters keep the case you type, and symbols are allowed.',
                $this->minLength(),
                $this->maxLength(),
            ),
            self::Email => 'The address your email broadcasts are sent from. SPF and DKIM records are verified during review.',
            self::Voice => 'The number recipients see, in international format. Approved numbers are provisioned as caller IDs.',
        };
    }

    /**
     * The shortest identifier this channel accepts.
     */
    public function minLength(): int
    {
        return match ($this) {
            self::Sms => max(1, (int) config('sender-ids.sms.min_length', 3)),
            default => 1,
        };
    }

    /**
     * The longest identifier this channel accepts, spaces included.
     */
    public function maxLength(): int
    {
        return match ($this) {
            self::Sms => max($this->minLength(), (int) config('sender-ids.sms.max_length', 11)),
            self::Email => min(120, max(1, (int) config('sender-ids.email.max_length', 120))),
            self::Voice => 20,
        };
    }

    /**
     * Whether spaces belong to the identifier on this channel.
     */
    public function allowsSpaces(): bool
    {
        return $this === self::Sms && (bool) config('sender-ids.sms.allow_spaces', true);
    }

    /**
     * A short explanation shown next to the channel selector.
     */
    public function description(): string
    {
        return match ($this) {
            self::Sms => 'Alphanumeric brand name shown on the recipient handset.',
            self::Email => 'Envelope from address used for email broadcasts.',
            self::Voice => 'Outbound caller ID used for voice broadcasts.',
        };
    }

    /**
     * Whether a country must be supplied for this channel.
     */
    public function requiresCountry(): bool
    {
        return $this !== self::Email;
    }
}
