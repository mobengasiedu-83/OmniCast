<?php

namespace App\Services;

use App\Contracts\VoiceGateway;
use Illuminate\Support\Facades\Log;

/**
 * Development voice transport: writes the call to the log instead of dialling out.
 *
 * Swap this binding for the real SIP/telephony driver in AppServiceProvider.
 */
class LogVoiceGateway implements VoiceGateway
{
    /**
     * Place an outbound call and report whether the switch accepted it.
     */
    public function call(string $to, string $from, string $script, ?string $recordingPath = null): bool
    {
        Log::info('Voice call placed', [
            'to' => $to,
            'from' => $from,
            'script' => $script,
            'recording' => $recordingPath,
        ]);

        return true;
    }
}
