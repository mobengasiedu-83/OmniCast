<?php

namespace App\Services\Voice;

use App\Contracts\VoiceGateway;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Originates calls through the Asterisk REST Interface.
 *
 * ARI takes a plain HTTP POST per call; the channel then runs the Stasis
 * application named in the payload, which posts its events back to the signed
 * voice webhook just like the AMI dialplan does.
 */
class AsteriskAriGateway implements VoiceGateway
{
    protected string $url;

    protected string $app;

    protected string $endpoint;

    public function __construct(
        ?string $url = null,
        protected ?string $username = null,
        protected ?string $secret = null,
        ?string $app = null,
        ?string $endpoint = null,
        protected int $timeout = 10,
    ) {
        $this->url = rtrim($url ?? (string) config('voice.ari.url', 'http://127.0.0.1:8088/ari'), '/');
        $this->app = $app ?? (string) config('voice.ari.app', 'omnicast');
        $this->endpoint = $endpoint ?? (string) config('voice.ari.endpoint', 'PJSIP');
    }

    /**
     * Place an outbound call and report whether the switch accepted it.
     */
    public function dial(VoiceCall $call): VoiceDispatchResult
    {
        try {
            $response = Http::withBasicAuth((string) $this->username, (string) $this->secret)
                ->acceptJson()
                ->asJson()
                ->timeout($this->timeout)
                ->post($this->url.'/channels', [
                    'endpoint' => $this->endpoint.'/'.$call->to,
                    'app' => $this->app,
                    'appArgs' => $call->reference,
                    'callerId' => $call->from,
                    'timeout' => $call->maxDurationSeconds,
                    'variables' => $call->variables(),
                ]);
        } catch (ConnectionException $exception) {
            return VoiceDispatchResult::rejected('The Asterisk REST interface is unreachable: '.$exception->getMessage());
        }

        if ($response->successful()) {
            return VoiceDispatchResult::accepted((string) ($response->json('id') ?? ''));
        }

        return VoiceDispatchResult::rejected(
            'Asterisk rejected the call: '.($response->json('message') ?? $response->status())
        );
    }

    /**
     * The transport name written onto each call row.
     */
    public function name(): string
    {
        return 'ari';
    }
}
