<?php

namespace App\Services\Billing;

use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\PlatformConfiguration;

/**
 * The "top up your wallet" catalogue from `config/billing.php`.
 *
 * A package is a bundle of pre-paid credit: buying it credits the wallet with
 * the package price, and the per-service allocation says how far that price
 * stretches on SMS, voice and email.
 *
 * Whether a package is on sale is decided per package: the `enabled` flag in
 * the configuration is the default, and a `billing.<key>.enabled` platform
 * setting — written by the Admin → Wallet packages screen — overrides it. A
 * hidden package keeps its price and allocation but leaves the price list and
 * is refused at checkout.
 */
class PackageCatalogue
{
    /**
     * The platform setting group that records package availability.
     */
    private const SETTINGS_GROUP = 'billing';

    public function __construct(
        protected PlatformConfiguration $configuration,
        protected CreditEstimator $estimator,
    ) {}

    /**
     * The packages on sale, in the order they are configured.
     *
     * @return list<array{
     *     key: string,
     *     name: string,
     *     price: float,
     *     popular: bool,
     *     tagline: string,
     *     units: list<array{service: string, label: string, quantity: int, formatted: string}>
     * }>
     */
    public function all(): array
    {
        $packages = [];

        foreach ($this->raw() as $key => $package) {
            if (! $this->isEnabled((string) $key, $package)) {
                continue;
            }

            $packages[] = $this->present((string) $key, $package);
        }

        return $packages;
    }

    /**
     * Every configured package with its availability, hidden ones included.
     *
     * @return list<array{
     *     key: string,
     *     name: string,
     *     price: float,
     *     popular: bool,
     *     tagline: string,
     *     units: list<array{service: string, label: string, quantity: int, formatted: string}>,
     *     enabled: bool
     * }>
     */
    public function every(): array
    {
        $packages = [];

        foreach ($this->raw() as $key => $package) {
            $packages[] = [
                ...$this->present((string) $key, $package),
                'enabled' => $this->isEnabled((string) $key, $package),
            ];
        }

        return $packages;
    }

    /**
     * The catalogue as select options.
     *
     * @return list<array{value: string, label: string}>
     */
    public function options(): array
    {
        return array_map(
            fn (array $package): array => [
                'value' => $package['key'],
                'label' => $package['name'].' · '.$this->money($package['price']),
            ],
            $this->all(),
        );
    }

    /**
     * The keys a checkout may submit.
     *
     * @return list<string>
     */
    public function keys(): array
    {
        $keys = [];

        foreach ($this->raw() as $key => $package) {
            if ($this->isEnabled((string) $key, $package)) {
                $keys[] = (string) $key;
            }
        }

        return $keys;
    }

    /**
     * One package in its presented shape, or null when the key is unknown.
     *
     * @return array{
     *     key: string,
     *     name: string,
     *     price: float,
     *     popular: bool,
     *     tagline: string,
     *     units: list<array{service: string, label: string, quantity: int, formatted: string}>
     * }|null
     */
    public function find(string $key): ?array
    {
        $package = $this->raw()[$key] ?? null;

        return $package === null ? null : $this->present($key, $package);
    }

    /**
     * One package as it may be bought right now, or null when the key is
     * unknown or the package is switched off.
     *
     * @return array{
     *     key: string,
     *     name: string,
     *     price: float,
     *     popular: bool,
     *     tagline: string,
     *     units: list<array{service: string, label: string, quantity: int, formatted: string}>
     * }|null
     */
    public function findPurchasable(string $key): ?array
    {
        $package = $this->raw()[$key] ?? null;

        if ($package === null || ! $this->isEnabled($key, $package)) {
            return null;
        }

        return $this->present($key, $package);
    }

    /**
     * Whether the catalogue is configured with this key, on sale or not.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->raw());
    }

    /**
     * Whether the platform is currently selling this package.
     */
    public function isEnabled(string $key, ?array $package = null): bool
    {
        $package ??= $this->raw()[$key] ?? null;

        if ($package === null) {
            return false;
        }

        $default = (bool) ($package['enabled'] ?? true);

        return (bool) filter_var(
            $this->configuration->resolve(self::SETTINGS_GROUP, $key.'.enabled', $default),
            FILTER_VALIDATE_BOOL,
        );
    }

    /**
     * Put a package on sale or take it off, overriding the configured default.
     */
    public function setEnabled(string $key, bool $enabled, ?User $user = null): void
    {
        $setting = PlatformSetting::query()->firstOrNew([
            'group' => self::SETTINGS_GROUP,
            'key' => $key.'.enabled',
        ]);

        $setting->value = $enabled ? '1' : '0';
        $setting->is_secret = false;
        $setting->updated_by = $user?->id;
        $setting->save();
    }

    /**
     * The line written on the payment, ledger entry and invoice.
     */
    public function describe(string $key): string
    {
        $package = $this->find($key);

        if ($package === null) {
            return 'Wallet top up';
        }

        $allocations = array_map(
            fn (array $unit): string => $unit['formatted'].' '.$unit['label'],
            $package['units'],
        );

        return $package['name'].' package · '.implode(', ', $allocations);
    }

    /**
     * The smallest and largest top up a client may start.
     *
     * @return array{min: float, max: float}
     */
    public function limits(): array
    {
        return $this->estimator->limits();
    }

    /**
     * The currency packages are quoted in.
     */
    public function currency(): string
    {
        return $this->estimator->currency();
    }

    /**
     * The raw catalogue as configured.
     *
     * @return array<string, array{name: string, price: float, popular?: bool, tagline?: string, units?: array<string, int>, enabled?: bool}>
     */
    protected function raw(): array
    {
        $packages = config('billing.packages', []);

        return is_array($packages) ? $packages : [];
    }

    /**
     * Shape one configured package for the page or a message.
     *
     * @param  array{name?: string, price?: float|int, popular?: bool, tagline?: string, units?: array<string, int>, enabled?: bool}  $package
     * @return array{
     *     key: string,
     *     name: string,
     *     price: float,
     *     popular: bool,
     *     tagline: string,
     *     units: list<array{service: string, label: string, quantity: int, formatted: string}>
     * }
     */
    protected function present(string $key, array $package): array
    {
        $price = round((float) ($package['price'] ?? 0), 2);

        return [
            'key' => $key,
            'name' => (string) ($package['name'] ?? ucfirst($key)),
            'price' => $price,
            'popular' => (bool) ($package['popular'] ?? false),
            'tagline' => (string) ($package['tagline'] ?? ''),
            'units' => $this->units($package, $price),
        ];
    }

    /**
     * The allocation shown on the package card.
     *
     * A package that states its own `units` keeps them: that is how a
     * deliberately sweetened bundle such as the ten-pesewa sample carries a
     * larger allocation than its price buys. Every other package is quoted from
     * the published rates, exactly as the amount slider quotes a custom top up,
     * so the card and the slider never disagree.
     *
     * @param  array{units?: array<string, int>}  $package
     * @return list<array{service: string, label: string, quantity: int, formatted: string}>
     */
    protected function units(array $package, float $price): array
    {
        $configured = $package['units'] ?? null;

        $lines = is_array($configured) && $configured !== []
            ? $this->estimator->lines($configured)
            : $this->estimator->estimate($price)['lines'];

        return array_values(array_map($this->presentUnit(...), $lines));
    }

    /**
     * One allocation in the shape the package card and the admin screen read.
     *
     * @param  array{service: string, label: string, quantity: int, formatted: string}  $line
     * @return array{service: string, label: string, quantity: int, formatted: string}
     */
    protected function presentUnit(array $line): array
    {
        return [
            'service' => $line['service'],
            'label' => $line['label'],
            'quantity' => $line['quantity'],
            'formatted' => $line['formatted'],
        ];
    }

    /**
     * A price with its currency, for labels built on the server.
     */
    protected function money(float $amount): string
    {
        return $this->currency().' '.number_format($amount, 2);
    }
}
