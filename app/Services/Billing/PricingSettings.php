<?php

namespace App\Services\Billing;

use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\PlatformConfiguration;

/**
 * The published price list, and whether clients may name their own amount.
 *
 * `config/billing.php` ships the platform's defaults: the selling rate of each
 * channel, the top up limits and the range the amount slider is drawn over. The
 * platform team changes them from Admin → Dynamic pricing, which stores each
 * value as a `billing.*` platform setting that wins over the configuration, so a
 * price change does not need a deployment.
 *
 * The two states that matter to a buyer are published from here:
 *
 *   dynamic_pricing — whether the "choose your own amount" slider is on sale.
 *                     Switched off, the wallet only sells catalogue packages
 *                     and a submitted amount is refused.
 *   rates / limits  — what one SMS part, voice minute or email sells for, and
 *                     how small or large a top up may be.
 */
class PricingSettings
{
    /**
     * The platform setting group every price lives in.
     */
    private const SETTINGS_GROUP = 'billing';

    /**
     * The billable services, in the order they are quoted.
     *
     * @var list<string>
     */
    public const SERVICES = ['sms', 'voice', 'email'];

    public function __construct(protected PlatformConfiguration $configuration) {}

    /**
     * Whether a client may choose the amount they pay.
     */
    public function dynamicEnabled(): bool
    {
        return filter_var(
            $this->configuration->resolve(
                self::SETTINGS_GROUP,
                'dynamic_pricing.enabled',
                $this->defaults()['dynamic_pricing'],
            ),
            FILTER_VALIDATE_BOOL,
        );
    }

    /**
     * The selling rate of one service, or 0 when the platform does not sell it.
     */
    public function rate(string $service): float
    {
        $rate = $this->configuration->resolve(
            self::SETTINGS_GROUP,
            'rates.'.$service,
            config('billing.rates.'.$service, 0),
        );

        return max(0.0, (float) $rate);
    }

    /**
     * The smallest and largest top up a client may pay.
     *
     * @return array{min: float, max: float}
     */
    public function limits(): array
    {
        return [
            'min' => (float) $this->configuration->resolve(
                self::SETTINGS_GROUP,
                'limits.min_top_up',
                config('billing.limits.min_top_up', 1),
            ),
            'max' => (float) $this->configuration->resolve(
                self::SETTINGS_GROUP,
                'limits.max_top_up',
                config('billing.limits.max_top_up', 100000),
            ),
        ];
    }

    /**
     * The range the amount slider is drawn over, with the amount it starts on.
     *
     * The slider sits inside the top up limits: it covers the amounts most
     * clients pay, while a typed amount may still use the whole range.
     *
     * @return array{min: float, max: float, step: float, default: float}
     */
    public function slider(): array
    {
        $limits = $this->limits();

        $min = max($limits['min'], $this->sliderValue('min'));
        $max = min($limits['max'], $this->sliderValue('max'));

        if ($max < $min) {
            $max = $min;
        }

        $step = max(0.01, $this->sliderValue('step'));
        $default = $this->sliderValue('default');

        return [
            'min' => round($min, 2),
            'max' => round($max, 2),
            'step' => round($step, 2),
            'default' => round(min(max($default, $min), $max), 2),
        ];
    }

    /**
     * The values `config/billing.php` publishes, before any admin override.
     *
     * @return array{
     *     dynamic_pricing: bool,
     *     rates: array<string, float>,
     *     limits: array{min: float, max: float},
     *     slider: array{min: float, max: float, step: float, default: float}
     * }
     */
    public function defaults(): array
    {
        $rates = [];

        foreach (self::SERVICES as $service) {
            $rates[$service] = (float) config('billing.rates.'.$service, 0);
        }

        return [
            'dynamic_pricing' => (bool) config('billing.dynamic_pricing', true),
            'rates' => $rates,
            'limits' => [
                'min' => (float) config('billing.limits.min_top_up', 1),
                'max' => (float) config('billing.limits.max_top_up', 100000),
            ],
            'slider' => [
                'min' => (float) config('billing.limits.slider.min', 10),
                'max' => (float) config('billing.limits.slider.max', 5000),
                'step' => (float) config('billing.limits.slider.step', 10),
                'default' => (float) config('billing.limits.slider.default', 100),
            ],
        ];
    }

    /**
     * The overrides the platform team has stored, keyed by setting key.
     *
     * Only the values that were actually written appear, so the admin screen can
     * tell an override from a default.
     *
     * @return array<string, string>
     */
    public function overrides(): array
    {
        return PlatformSetting::query()
            ->where('group', self::SETTINGS_GROUP)
            ->whereIn('key', $this->keys())
            ->get()
            ->mapWithKeys(fn (PlatformSetting $setting): array => [$setting->key => (string) $setting->value])
            ->all();
    }

    /**
     * Store a new price list, and whether the slider is on sale.
     *
     * @param  array{
     *     dynamic_pricing?: bool,
     *     rates?: array<string, float>,
     *     limits?: array{min?: float, max?: float},
     *     slider?: array{min?: float, max?: float, step?: float, default?: float}
     * }  $values
     */
    public function save(array $values, ?User $user = null): void
    {
        $settings = [
            'dynamic_pricing.enabled' => ($values['dynamic_pricing'] ?? $this->dynamicEnabled()) ? '1' : '0',
        ];

        foreach (self::SERVICES as $service) {
            if (isset($values['rates'][$service])) {
                $settings['rates.'.$service] = $this->decimal((float) $values['rates'][$service], 4);
            }
        }

        foreach (['min' => 'limits.min_top_up', 'max' => 'limits.max_top_up'] as $key => $setting) {
            if (isset($values['limits'][$key])) {
                $settings[$setting] = $this->decimal((float) $values['limits'][$key]);
            }
        }

        foreach (['min', 'max', 'step', 'default'] as $key) {
            if (isset($values['slider'][$key])) {
                $settings['slider.'.$key] = $this->decimal((float) $values['slider'][$key]);
            }
        }

        foreach ($settings as $key => $value) {
            $setting = PlatformSetting::query()->firstOrNew([
                'group' => self::SETTINGS_GROUP,
                'key' => $key,
            ]);

            $setting->value = $value;
            $setting->is_secret = false;
            $setting->updated_by = $user?->id;
            $setting->save();
        }
    }

    /**
     * The platform setting keys a price list is made of.
     *
     * @return list<string>
     */
    public function keys(): array
    {
        $keys = ['dynamic_pricing.enabled', 'limits.min_top_up', 'limits.max_top_up'];

        foreach (self::SERVICES as $service) {
            $keys[] = 'rates.'.$service;
        }

        foreach (['min', 'max', 'step', 'default'] as $key) {
            $keys[] = 'slider.'.$key;
        }

        return $keys;
    }

    /**
     * One slider bound, from the override or from the configuration.
     */
    protected function sliderValue(string $key): float
    {
        return (float) $this->configuration->resolve(
            self::SETTINGS_GROUP,
            'slider.'.$key,
            config('billing.limits.slider.'.$key, 0),
        );
    }

    /**
     * A stored number without the float noise (`0.30000000000000004`).
     */
    protected function decimal(float $value, int $precision = 2): string
    {
        return rtrim(rtrim(number_format($value, $precision, '.', ''), '0'), '.') ?: '0';
    }
}
