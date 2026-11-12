<?php

namespace App\Services;

use App\Models\Client;
use App\Models\PlatformSetting;

/** Resolves configuration in order: global defaults, plan defaults, then tenant overrides. */
class PlatformConfiguration
{
    public function resolve(string $group, string $key, mixed $default = null, ?Client $client = null, array $plan = []): mixed
    {
        $setting = PlatformSetting::query()->where('group', $group)->where('key', $key)->first();
        $value = $setting?->value ?? $default;

        $planCode = $client?->settings['plan_code'] ?? 'default';
        if (is_string($planCode) && $planCode !== '') {
            $planSetting = PlatformSetting::query()
                ->where('group', 'plans')
                ->where('key', $planCode.'.'.$group.'.'.$key)
                ->first()?->value;
            if ($planSetting !== null) {
                $value = $planSetting;
            }
        }

        $planSettings = $plan[$group] ?? [];
        if (is_array($planSettings) && array_key_exists($key, $planSettings)) {
            $value = $planSettings[$key];
        }

        $overrides = $client?->settings['overrides'] ?? [];

        if (is_array($overrides) && array_key_exists($group.'.'.$key, $overrides)) {
            $value = $overrides[$group.'.'.$key];
        } elseif (is_array($overrides[$group] ?? null) && array_key_exists($key, $overrides[$group])) {
            $value = $overrides[$group][$key];
        }

        return $value;
    }

    public function enabled(string $key, bool $default = true, ?Client $client = null): bool
    {
        return filter_var($this->resolve('feature_flags', $key, $default, $client), FILTER_VALIDATE_BOOL);
    }

    /**
     * Whether the platform team still offers a delivery module to any tenant.
     *
     * This is the universal switch the module screen writes. It is read
     * straight from the setting rather than through the plan chain, because
     * disabling a module has to take it away from every tenant at once.
     */
    public function moduleEnabled(string $module): bool
    {
        $setting = PlatformSetting::query()
            ->where('group', 'channels')
            ->where('key', $module.'.enabled')
            ->first();

        return $setting === null
            || filter_var($setting->value, FILTER_VALIDATE_BOOL);
    }

    public function channelEnabled(string $channel, ?Client $client = null): bool
    {
        if (! $this->moduleEnabled($channel)) {
            return false;
        }

        return $this->enabled('channels.'.$channel, true, $client);
    }

    /**
     * Whether a reviewer still has to approve a campaign before it can launch.
     */
    public function campaignApprovalRequired(?Client $client = null): bool
    {
        return filter_var(
            $this->resolve('campaigns', 'require_approval', config('campaigns.require_approval', false), $client),
            FILTER_VALIDATE_BOOL,
        );
    }

    /**
     * Whether voice audio needs a reviewer before the campaign may run.
     */
    public function audioApprovalRequired(?Client $client = null): bool
    {
        return filter_var(
            $this->resolve('campaigns', 'require_approved_audio', config('campaigns.require_approved_audio', true), $client),
            FILTER_VALIDATE_BOOL,
        );
    }
}
