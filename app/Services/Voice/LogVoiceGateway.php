<?php

namespace App\Services\Voice;

use App\Contracts\VoiceGateway;
use Illuminate\Support\Facades\Log;

/**
 * Development voice transport: writes the call to the log instead of dialling out.
 *
 * The default driver, so a local checkout can run the voice campaign flow end to
 * end without a PBX. Swap it with VOICE_DRIVER=ami or VOICE_DRIVER=ari.
 */
class LogVoiceGateway implements VoiceGateway
{
    /**
     * Place an outbound call and report whether the switch accepted it.
     */
    public function dial(VoiceCall $call): VoiceDispatchResult
    {
        Log::info('Voice call placed', [
            'reference' => $call->reference,
            'to' => $call->to,
            'from' => $call->from,
            'script' => $call->script,
            'recording' => $call->recordingPath,
            'ivr' => $call->ivr?->toArray(),
            'max_duration_seconds' => $call->maxDurationSeconds,
        ]);

        return VoiceDispatchResult::accepted('log-'.$call->reference);
    }

    /**
     * The transport name written onto each call row.
     */
    public function name(): string
    {
        return 'log';
    }
}
