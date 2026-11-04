<?php

namespace App\Services\Voice;

use App\Contracts\VoiceGateway;
use App\Services\PlatformConfiguration;
use Illuminate\Contracts\Container\Container;

/**
 * Resolves the voice transport the platform is configured to dial with.
 *
 * The driver can be set in `config/voice.php` and overridden per deployment with
 * the `voice.driver` platform setting, so an operator can move a tenant from the
 * log transport to the PBX without a code change.
 */
class VoiceGatewayManager
{
    public function __construct(protected Container $container) {}

    /**
     * The transport every voice campaign is dialled through.
     */
    public function driver(): VoiceGateway
    {
        $driver = mb_strtolower(trim((string) $this->container->make(PlatformConfiguration::class)->resolve(
            'voice',
            'driver',
            config('voice.driver', 'log'),
        )));

        if (in_array($driver, ['ami', 'ari'], true) && ! config('voice.broadcast.enabled')) {
            return new DisabledVoiceGateway(
                'Voice broadcasting is disabled by platform settings (VOICE_BROADCAST_ENABLED).'
            );
        }

        return match ($driver) {
            'ami' => $this->container->make(AsteriskAmiGateway::class),
            'ari' => $this->container->make(AsteriskAriGateway::class),
            default => $this->container->make(LogVoiceGateway::class),
        };
    }
}
