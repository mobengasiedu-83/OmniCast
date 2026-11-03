<?php

namespace App\Contracts;

/**
 * The outbound voice transport used when a campaign is dispatched.
 */
interface VoiceGateway
{
    /**
     * Place an outbound call and report whether the switch accepted it.
     */
    public function call(string $to, string $from, string $script, ?string $recordingPath = null): bool;
}
