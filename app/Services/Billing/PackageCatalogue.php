<?php

namespace App\Services\Billing;

/**
 * The "top up your wallet" catalogue from `config/billing.php`.
 *
 * A package is a bundle of pre-paid credit: buying it credits the wallet with
 * the package price, and the per-service allocation says how far that price
 * stretches on SMS, voice and email.
 */
class PackageCatalogue
{
    /**
     * The service labels and the order they are shown in.
     *
     * @var array<string, string>
     */
    private const SERVICES = [
        'sms' => 'SMS',
        'voice' => 'Voice Calls',
        'email' => 'Emails',
    ];

    /**
     * The whole catalogue, in the order it is configured.
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
            $packages[] = $this->present((string) $key, $package);
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
        return array_map('strval', array_keys($this->raw()));
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
        return [
            'min' => (float) config('billing.limits.min_top_up', 1),
            'max' => (float) config('billing.limits.max_top_up', 100000),
        ];
    }

    /**
     * The currency packages are quoted in.
     */
    public function currency(): string
    {
        return (string) config('billing.currency', 'GHS');
    }

    /**
     * The raw catalogue as configured.
     *
     * @return array<string, array{name: string, price: float, popular?: bool, tagline?: string, units?: array<string, int>}>
     */
    protected function raw(): array
    {
        $packages = config('billing.packages', []);

        return is_array($packages) ? $packages : [];
    }

    /**
     * Shape one configured package for the page or a message.
     *
     * @param  array{name?: string, price?: float|int, popular?: bool, tagline?: string, units?: array<string, int>}  $package
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
        $units = [];

        foreach (self::SERVICES as $service => $label) {
            $quantity = (int) ($package['units'][$service] ?? 0);

            if ($quantity <= 0) {
                continue;
            }

            $units[] = [
                'service' => $service,
                'label' => $label,
                'quantity' => $quantity,
                'formatted' => number_format($quantity),
            ];
        }

        return [
            'key' => $key,
            'name' => (string) ($package['name'] ?? ucfirst($key)),
            'price' => round((float) ($package['price'] ?? 0), 2),
            'popular' => (bool) ($package['popular'] ?? false),
            'tagline' => (string) ($package['tagline'] ?? ''),
            'units' => $units,
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
