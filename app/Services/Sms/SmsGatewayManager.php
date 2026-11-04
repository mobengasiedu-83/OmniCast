<?php

namespace App\Services\Sms;

use App\Contracts\SmsGateway;
use App\Services\PlatformConfiguration;
use Illuminate\Contracts\Container\Container;

/**
 * Resolves the SMS transport the platform is configured to broadcast with.
 *
 * The driver can be set in `config/sms.php` and overridden per deployment with
 * the `sms.driver` platform setting, so an operator can move the platform from
 * the log transport to a provider without a code change. Live providers stay
 * unreachable until SMS_BROADCAST_ENABLED is switched on.
 */
class SmsGatewayManager
{
    public function __construct(protected Container $container) {}

    /**
     * The transport every SMS campaign is submitted through.
     */
    public function driver(): SmsGateway
    {
        $driver = mb_strtolower(trim((string) $this->container->make(PlatformConfiguration::class)->resolve(
            'sms',
            'driver',
            config('sms.driver', 'log'),
        )));

        if ($driver !== 'log' && ! config('sms.broadcast.enabled')) {
            return new DisabledSmsGateway(
                'SMS broadcasting is disabled by platform settings (SMS_BROADCAST_ENABLED).'
            );
        }

        return match ($driver) {
            'txtconnect' => $this->container->make(TxtConnectSmsGateway::class),
            'generic_json' => $this->container->make(GenericJsonSmsGateway::class),
            'kannel' => $this->container->make(KannelSmsGateway::class),
            default => $this->container->make(LogSmsGateway::class),
        };
    }
}
