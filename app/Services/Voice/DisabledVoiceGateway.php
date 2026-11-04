<?php

namespace App\Services\Voice;

use App\Contracts\VoiceGateway;
use Illuminate\Support\Facades\Log;

/**
 * Refuses every call while live voice broadcasting is switched off.
 *
 * `VOICE_BROADCAST_ENABLED` must stay off until the PBX dialplan, the audio and
 * the carrier have been through controlled UAT; pointing the platform at a real
 * PBX driver without that evidence fails the call instead of dialling blind.
 */
class DisabledVoiceGateway implements VoiceGateway
{
    public function __construct(protected string $reason) {}

    /**
     * Place an outbound call and report whether the switch accepted it.
     */
    public function dial(VoiceCall $call): VoiceDispatchResult
    {
        Log::warning('Voice call refused', [
            'reference' => $call->reference,
            'to' => $call->to,
            'reason' => $this->reason,
        ]);

        return VoiceDispatchResult::rejected($this->reason);
    }

    /**
     * The transport name written onto each call row.
     */
    public function name(): string
    {
        return 'disabled';
    }
}
