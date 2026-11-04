<?php

namespace App\Services;

use App\Enums\VoiceIvrAction;
use App\Models\Campaign;
use App\Services\Voice\VoiceIvrPlan;

/**
 * Builds the DTMF menu a voice campaign dials out with.
 *
 * A campaign is not required to have a menu: without one the switch simply
 * plays the recording (or the script) and hangs up. The opt-out digit is always
 * part of the menu, so a recipient can always leave the list from the handset.
 */
class VoiceIvr
{
    /**
     * The menu for this campaign, or null when it plays no interactive menu.
     */
    public function planFor(Campaign $campaign): ?VoiceIvrPlan
    {
        if (! $campaign->ivr_enabled) {
            return null;
        }

        $prompt = trim((string) ($campaign->ivr_prompt ?: $campaign->voice_script));

        if ($prompt === '') {
            return null;
        }

        return new VoiceIvrPlan(
            prompt: $prompt,
            options: $this->options($campaign),
            timeoutSeconds: max(1, (int) ($campaign->ivr_timeout_seconds ?: config('voice.ivr.timeout_seconds', 6))),
            maxAttempts: max(1, (int) ($campaign->ivr_max_attempts ?: config('voice.ivr.max_attempts', 3))),
            optOutDigit: $this->optOutDigit(),
        );
    }

    /**
     * The digit that removes a recipient from future broadcasts.
     */
    public function optOutDigit(): string
    {
        $digit = preg_replace('/\D/', '', (string) config('voice.ivr.opt_out_digit', '9')) ?? '';

        return $digit === '' ? '9' : mb_substr($digit, 0, 1);
    }

    /**
     * The campaign's digit map, falling back to the platform default.
     *
     * @return array<string, VoiceIvrAction>
     */
    protected function options(Campaign $campaign): array
    {
        $configured = $campaign->ivr_options ?? config('voice.ivr.options', []);
        $options = [];

        foreach ((array) $configured as $digit => $action) {
            $digit = preg_replace('/\D/', '', (string) $digit) ?? '';
            $resolved = is_string($action) ? VoiceIvrAction::fromConfig($action) : null;

            if ($digit === '' || $resolved === null) {
                continue;
            }

            $options[mb_substr($digit, 0, 1)] = $resolved;
        }

        // A recipient must always be able to leave the menu.
        $options[$this->optOutDigit()] = VoiceIvrAction::OptOut;

        return $options;
    }
}
