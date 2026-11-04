<?php

namespace App\Services\Voice;

use App\Enums\VoiceIvrAction;

/**
 * The DTMF menu a voice campaign plays before (or instead of) the message.
 *
 * The plan is handed to the switch as dialplan variables, and the same plan
 * decides what a digit pressed on a callback means, so both halves of the IVR
 * can never drift apart.
 */
final class VoiceIvrPlan
{
    /**
     * @param  array<string, VoiceIvrAction>  $options  digit => action
     */
    public function __construct(
        public readonly string $prompt,
        public readonly array $options,
        public readonly int $timeoutSeconds = 6,
        public readonly int $maxAttempts = 3,
        public readonly string $optOutDigit = '9',
    ) {}

    /**
     * The action a key press stands for, taking the last digit of the string.
     */
    public function resolve(?string $digits): VoiceIvrAction
    {
        $digit = $this->lastDigit($digits);

        if ($digit === null) {
            return VoiceIvrAction::Unrecognised;
        }

        return $this->options[$digit] ?? VoiceIvrAction::Unrecognised;
    }

    /**
     * The key the recipient actually pressed.
     */
    public function lastDigit(?string $digits): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $digits) ?? '';

        return $digits === '' ? null : mb_substr($digits, -1);
    }

    /**
     * The variables the dialplan uses to run the menu.
     *
     * @return array<string, string>
     */
    public function variables(): array
    {
        return [
            'OMNICAST_IVR' => '1',
            'OMNICAST_IVR_PROMPT' => trim(preg_replace('/\s+/', ' ', $this->prompt) ?? ''),
            'OMNICAST_IVR_OPTIONS' => implode(',', array_keys($this->options)),
            'OMNICAST_IVR_TIMEOUT' => (string) $this->timeoutSeconds,
            'OMNICAST_IVR_ATTEMPTS' => (string) $this->maxAttempts,
            'OMNICAST_IVR_OPT_OUT' => $this->optOutDigit,
        ];
    }

    /**
     * The plan as it is frozen onto the call row for auditing.
     *
     * @return array{prompt: string, options: array<string, string>, timeout_seconds: int, max_attempts: int, opt_out_digit: string}
     */
    public function toArray(): array
    {
        return [
            'prompt' => $this->prompt,
            'options' => array_map(fn (VoiceIvrAction $action): string => $action->value, $this->options),
            'timeout_seconds' => $this->timeoutSeconds,
            'max_attempts' => $this->maxAttempts,
            'opt_out_digit' => $this->optOutDigit,
        ];
    }
}
