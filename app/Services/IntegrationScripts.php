<?php

namespace App\Services;

use App\Enums\Integration;
use App\Models\PlatformSetting;

/**
 * The tracking snippets the platform team pasted into the admin.
 *
 * Same store as every other runtime setting: one `platform_settings` row per
 * slot (`{integration}.head`, `{integration}.body`, `{integration}.enabled`),
 * encrypted at rest and attributed to the operator who saved it. A snippet is
 * only printed when its integration is switched on, so a tag can be parked
 * without losing the code.
 */
class IntegrationScripts
{
    /**
     * The setting group the snippets are stored under.
     */
    public const GROUP = 'integrations';

    /**
     * The saved state of every integration, resolved once per instance.
     *
     * @var array<string, array{head: ?string, body: ?string, enabled: bool}>|null
     */
    private ?array $state = null;

    /**
     * The saved state of every integration, keyed by integration value.
     *
     * @return array<string, array{head: ?string, body: ?string, enabled: bool}>
     */
    public function state(): array
    {
        if ($this->state !== null) {
            return $this->state;
        }

        $state = [];

        foreach (Integration::cases() as $integration) {
            $state[$integration->value] = ['head' => null, 'body' => null, 'enabled' => false];
        }

        $settings = PlatformSetting::query()
            ->where('group', self::GROUP)
            ->get(['key', 'value']);

        foreach ($settings as $setting) {
            [$value, $slot] = array_pad(explode('.', (string) $setting->key, 2), 2, null);

            if ($slot === null || ! array_key_exists($value, $state)) {
                continue;
            }

            if ($slot === 'enabled') {
                $state[$value]['enabled'] = filter_var($setting->value, FILTER_VALIDATE_BOOL);
            } elseif ($slot === 'head' || $slot === 'body') {
                $state[$value][$slot] = filled($setting->value) ? (string) $setting->value : null;
            }
        }

        return $this->state = $state;
    }

    /**
     * The snippets an enabled integration prints, split by where they belong.
     *
     * @return array{head: list<string>, body: list<string>}
     */
    public function snippets(): array
    {
        $head = [];
        $body = [];

        foreach ($this->state() as $integration) {
            if (! $integration['enabled']) {
                continue;
            }

            if ($integration['head'] !== null) {
                $head[] = $integration['head'];
            }

            if ($integration['body'] !== null) {
                $body[] = $integration['body'];
            }
        }

        return ['head' => $head, 'body' => $body];
    }

    /**
     * Store one integration's code, blanks included.
     *
     * Clearing a box empties the slot rather than deleting the row, so the
     * activity log still shows the integration being touched.
     */
    public function save(Integration $integration, ?string $head, ?string $body, bool $enabled, ?int $userId): void
    {
        $this->store($integration->value.'.head', $head, $userId);
        $this->store($integration->value.'.body', $body, $userId);
        $this->store($integration->value.'.enabled', $enabled ? '1' : '0', $userId);

        $this->state = null;
    }

    /**
     * Write a single slot, trimming the paste so whitespace never decides
     * whether a snippet is considered saved.
     */
    private function store(string $key, ?string $value, ?int $userId): void
    {
        $trimmed = $value === null ? '' : trim($value);

        $setting = PlatformSetting::query()->firstOrNew([
            'group' => self::GROUP,
            'key' => $key,
        ]);

        $setting->value = $trimmed;
        $setting->is_secret = false;
        $setting->updated_by = $userId;
        $setting->save();
    }
}
