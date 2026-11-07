<?php

namespace App\Services;

use App\Enums\Channel;
use App\Enums\ClientService;
use App\Models\Client;

/**
 * The single answer to "is this module live for this tenant?".
 *
 * Two switches have to agree. The platform team holds the universal switch
 * that takes a module away from every client at once; each tenant then has its
 * own entitlement row that can take a module away from that tenant alone.
 * Entitlements are opt-out, so a tenant without a row keeps everything the
 * platform still offers.
 */
class ModuleAvailability
{
    public function __construct(private PlatformConfiguration $configuration) {}

    /**
     * Whether the platform team still offers the module to anyone at all.
     */
    public function universallyEnabled(ClientService $module): bool
    {
        return $this->configuration->moduleEnabled($module->value);
    }

    /**
     * Whether the module is live for the given tenant.
     */
    public function enabled(ClientService $module, ?Client $client = null): bool
    {
        if (! $this->universallyEnabled($module)) {
            return false;
        }

        return $client === null || $client->allowsService($module);
    }

    /**
     * Whether every module behind a campaign channel is live for the tenant.
     *
     * A multi-channel campaign needs both of its modules, which keeps it out of
     * reach as soon as either voice or SMS is switched off.
     */
    public function allowsChannel(Channel $channel, ?Client $client = null): bool
    {
        foreach ($channel->deliveryChannels() as $deliveryChannel) {
            $module = ClientService::fromChannel($deliveryChannel);

            if ($module !== null && ! $this->enabled($module, $client)) {
                return false;
            }
        }

        return true;
    }

    /**
     * The enabled state of every module, keyed by module value.
     *
     * @return array<string, bool>
     */
    public function forClient(?Client $client): array
    {
        if ($client !== null) {
            $client->loadMissing('entitlements');
        }

        $modules = [];

        foreach (ClientService::cases() as $module) {
            $modules[$module->value] = $this->enabled($module, $client);
        }

        return $modules;
    }

    /**
     * The campaign channels a tenant may still choose from.
     *
     * @return list<array{value: string, label: string}>
     */
    public function channelOptions(?Client $client = null): array
    {
        $options = [];

        foreach (Channel::cases() as $channel) {
            if (! $this->allowsChannel($channel, $client)) {
                continue;
            }

            $options[] = ['value' => $channel->value, 'label' => $channel->label()];
        }

        return $options;
    }
}
