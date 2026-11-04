<?php

namespace App\Services\Voice;

use App\Contracts\VoiceGateway;

/**
 * Originates calls through the Asterisk Manager Interface (VitalPBX).
 *
 * The action is asynchronous: the switch answers `Success` as soon as it queued
 * the call, and every later state arrives on the signed voice webhook. The
 * campaign reference is passed as both the extension and the ActionID so the
 * dialplan can post it back.
 */
class AsteriskAmiGateway implements VoiceGateway
{
    protected string $trunk;

    protected string $context;

    protected string $provider = 'ami';

    public function __construct(
        protected AmiTransport $transport,
        ?string $trunk = null,
        ?string $context = null,
    ) {
        $this->trunk = $trunk ?? (string) config('voice.ami.trunk', 'SIP/omnicast');
        $this->context = $context ?? (string) config('voice.ami.context', 'omnicast-outbound');
    }

    /**
     * Place an outbound call and report whether the switch accepted it.
     */
    public function dial(VoiceCall $call): VoiceDispatchResult
    {
        $variables = [];

        foreach ($call->variables() as $name => $value) {
            $variables[] = $name.'='.$value;
        }

        $response = $this->transport->execute('Originate', [
            'Channel' => $this->trunk.'/'.$call->to,
            'Exten' => $call->reference,
            'Context' => $this->context,
            'Priority' => '1',
            'CallerID' => $call->from,
            'Timeout' => (string) ($call->maxDurationSeconds * 1000),
            'Async' => 'true',
            'ActionID' => $call->reference,
            'Variable' => $variables,
        ]);

        if ($response === null) {
            return VoiceDispatchResult::rejected('The Asterisk manager interface did not answer.');
        }

        if (($response['Response'] ?? '') !== 'Success') {
            return VoiceDispatchResult::rejected(
                $response['Message'] ?? 'Asterisk rejected the originate action.'
            );
        }

        return VoiceDispatchResult::accepted($response['Uniqueid'] ?? null);
    }

    /**
     * The transport name written onto each call row.
     */
    public function name(): string
    {
        return $this->provider;
    }
}
