<?php

namespace App\Contracts;

use App\Services\Voice\VoiceCall;
use App\Services\Voice\VoiceDispatchResult;

/**
 * The outbound voice transport used when a campaign is dispatched.
 *
 * Implemented by the development log transport and by the Asterisk adapters
 * (Manager Interface and REST Interface) that dial through the platform PBX.
 */
interface VoiceGateway
{
    /**
     * Place an outbound call and report whether the switch accepted it.
     */
    public function dial(VoiceCall $call): VoiceDispatchResult;

    /**
     * The transport name recorded on each call row (log, ami, ari).
     */
    public function name(): string;
}
