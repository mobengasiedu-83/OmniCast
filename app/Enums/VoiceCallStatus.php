<?php

namespace App\Enums;

/**
 * Where an outbound call stands in its lifecycle.
 *
 * `queued` is written before the switch is contacted, `initiated` once it accepted
 * the originate action, and the remaining states are driven by the callbacks the
 * PBX posts back. Everything from `completed` onwards never changes again.
 */
enum VoiceCallStatus: string
{
    case Queued = 'queued';
    case Initiated = 'initiated';
    case Ringing = 'ringing';
    case Answered = 'answered';
    case Completed = 'completed';
    case NoAnswer = 'no_answer';
    case Busy = 'busy';
    case Failed = 'failed';
    case Canceled = 'canceled';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Queued',
            self::Initiated => 'Dialling',
            self::Ringing => 'Ringing',
            self::Answered => 'Answered',
            self::Completed => 'Completed',
            self::NoAnswer => 'No answer',
            self::Busy => 'Busy',
            self::Failed => 'Failed',
            self::Canceled => 'Cancelled',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Completed,
            self::NoAnswer,
            self::Busy,
            self::Failed,
            self::Canceled,
        ], true);
    }

    /**
     * Whether a human (or a machine) picked the call up.
     */
    public function wasAnswered(): bool
    {
        return in_array($this, [self::Answered, self::Completed], true);
    }

    /**
     * Whether the campaign may dial the recipient again.
     */
    public function isRetryable(): bool
    {
        return in_array($this, [self::NoAnswer, self::Busy, self::Failed], true);
    }
}
