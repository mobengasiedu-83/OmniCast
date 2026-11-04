<?php

namespace App\Enums;

/**
 * What a DTMF digit pressed on a voice call does.
 *
 * `unrecognised` is the fallback for a digit the menu does not map, so the
 * switch can replay the prompt instead of dropping the call.
 */
enum VoiceIvrAction: string
{
    case Connect = 'connect';
    case Repeat = 'repeat';
    case OptOut = 'opt_out';
    case Callback = 'callback';
    case Unrecognised = 'unrecognised';

    public function label(): string
    {
        return match ($this) {
            self::Connect => 'Connected to the message',
            self::Repeat => 'Replayed the message',
            self::OptOut => 'Opted out of future calls',
            self::Callback => 'Requested a callback',
            self::Unrecognised => 'Unrecognised key press',
        };
    }

    /**
     * Build an action from the value stored in configuration.
     */
    public static function fromConfig(string $value): ?self
    {
        return self::tryFrom(mb_strtolower(trim($value)));
    }
}
