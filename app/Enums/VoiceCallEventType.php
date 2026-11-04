<?php

namespace App\Enums;

/**
 * The callback events the switch posts to the voice webhook.
 */
enum VoiceCallEventType: string
{
    case Initiated = 'initiated';
    case Ringing = 'ringing';
    case Answered = 'answered';
    case Dtmf = 'dtmf';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Initiated => 'Initiated',
            self::Ringing => 'Ringing',
            self::Answered => 'Answered',
            self::Dtmf => 'Key press',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
        };
    }

    /**
     * The call state this event moves the call into, if any.
     */
    public function status(): ?VoiceCallStatus
    {
        return match ($this) {
            self::Initiated => VoiceCallStatus::Initiated,
            self::Ringing => VoiceCallStatus::Ringing,
            self::Answered => VoiceCallStatus::Answered,
            self::Completed => VoiceCallStatus::Completed,
            self::Failed => VoiceCallStatus::Failed,
            self::Dtmf => null,
        };
    }
}
