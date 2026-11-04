<?php

namespace App\Services\Email;

use App\Contracts\EmailGateway;
use App\Services\PlatformConfiguration;
use Illuminate\Contracts\Container\Container;

/**
 * Resolves the email transport the platform is configured to broadcast with.
 *
 * The driver can be set in `config/email.php` and overridden per deployment with
 * the `email.driver` platform setting, so an operator can move the platform from
 * the log transport to SMTP or an HTTP provider without a code change. Live
 * transports stay unreachable until EMAIL_BROADCAST_ENABLED is switched on.
 */
class EmailGatewayManager
{
    public function __construct(protected Container $container) {}

    /**
     * The transport every email campaign is submitted through.
     */
    public function driver(): EmailGateway
    {
        $driver = mb_strtolower(trim((string) $this->container->make(PlatformConfiguration::class)->resolve(
            'email',
            'driver',
            config('email.driver', 'log'),
        )));

        if ($driver !== 'log' && ! config('email.broadcast.enabled')) {
            return new DisabledEmailGateway(
                'Email broadcasting is disabled by platform settings (EMAIL_BROADCAST_ENABLED).'
            );
        }

        return match ($driver) {
            'smtp' => $this->container->make(SmtpEmailGateway::class),
            'generic_json' => $this->container->make(GenericJsonEmailGateway::class),
            default => $this->container->make(LogEmailGateway::class),
        };
    }
}
