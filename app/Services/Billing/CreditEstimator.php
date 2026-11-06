<?php

namespace App\Services\Billing;

/**
 * What an amount of wallet credit buys on each channel.
 *
 * The wallet holds money, not a channel, so one amount has to be quotable in
 * SMS parts, voice minutes and emails at the same time. The platform publishes
 * one selling rate per service (`billing.rates`), and a quantity is the amount
 * divided by that rate, rounded down so a client is never promised a unit the
 * money does not quite cover.
 *
 * The same arithmetic presents a wallet package's allocation, describes a
 * custom top up on the ledger and its invoice, and draws the slider that lets a
 * client choose the amount they want to pay.
 */
class CreditEstimator
{
    /**
     * The billable services, in the order they are quoted, with the unit each
     * one is counted in and the note printed beside it.
     *
     * @var array<string, array{label: string, unit: string, note: string}>
     */
    private const SERVICES = [
        'sms' => [
            'label' => 'SMS',
            'unit' => 'SMS parts',
            'note' => 'One unit is 160 characters to one number',
        ],
        'voice' => [
            'label' => 'Voice minutes',
            'unit' => 'minutes',
            'note' => 'One unit is a minute of the call to one number',
        ],
        'email' => [
            'label' => 'Emails',
            'unit' => 'emails',
            'note' => 'One unit is one email to one address',
        ],
    ];

    public function __construct(protected PricingSettings $settings) {}

    /**
     * Whether clients may choose the amount they pay instead of a package.
     *
     * The platform team switches the slider off from Admin → Dynamic pricing;
     * the wallet then only sells the catalogue packages.
     */
    public function dynamicPricingEnabled(): bool
    {
        return $this->settings->dynamicEnabled();
    }

    /**
     * The selling rate of every priced service.
     *
     * @return list<array{
     *     service: string,
     *     label: string,
     *     unit: string,
     *     note: string,
     *     unit_price: float,
     *     price_formatted: string
     * }>
     */
    public function rates(): array
    {
        $rates = [];

        foreach (self::SERVICES as $service => $unit) {
            $unitPrice = $this->unitPrice($service);

            if ($unitPrice <= 0.0) {
                continue;
            }

            $rates[] = [
                'service' => $service,
                'label' => $unit['label'],
                'unit' => $unit['unit'],
                'note' => $unit['note'],
                'unit_price' => $unitPrice,
                'price_formatted' => $this->money($unitPrice),
            ];
        }

        return $rates;
    }

    /**
     * What the amount buys on every channel.
     *
     * @return array{
     *     amount: float,
     *     currency: string,
     *     formatted: string,
     *     lines: list<array{
     *         service: string,
     *         label: string,
     *         unit: string,
     *         note: string,
     *         unit_price: float,
     *         price_formatted: string,
     *         quantity: int,
     *         formatted: string
     *     }>
     * }
     */
    public function estimate(float $amount): array
    {
        $amount = $this->clamp($amount);

        $lines = [];

        foreach ($this->rates() as $rate) {
            $quantity = $this->quantity($amount, $rate['unit_price']);

            $lines[] = [
                ...$rate,
                'quantity' => $quantity,
                'formatted' => number_format($quantity),
            ];
        }

        return [
            'amount' => round($amount, 2),
            'currency' => $this->currency(),
            'formatted' => $this->money($amount),
            'lines' => $lines,
        ];
    }

    /**
     * Present hand-picked quantities — a package that states its own allocation
     * rather than leaving it to the rate — in the shape `estimate()` returns.
     *
     * @param  array<string, int|float>  $quantities
     * @return list<array{
     *     service: string,
     *     label: string,
     *     unit: string,
     *     note: string,
     *     unit_price: float,
     *     price_formatted: string,
     *     quantity: int,
     *     formatted: string
     * }>
     */
    public function lines(array $quantities): array
    {
        $lines = [];

        foreach (self::SERVICES as $service => $unit) {
            $quantity = (int) ($quantities[$service] ?? 0);

            if ($quantity <= 0) {
                continue;
            }

            $unitPrice = $this->unitPrice($service);

            $lines[] = [
                'service' => $service,
                'label' => $unit['label'],
                'unit' => $unit['unit'],
                'note' => $unit['note'],
                'unit_price' => $unitPrice,
                'price_formatted' => $this->money($unitPrice),
                'quantity' => $quantity,
                'formatted' => number_format($quantity),
            ];
        }

        return $lines;
    }

    /**
     * The line written on the payment, its ledger entry and its invoice.
     */
    public function describe(float $amount): string
    {
        $estimate = $this->estimate($amount);

        $allocations = array_map(
            fn (array $line): string => $line['formatted'].' '.$line['label'],
            $estimate['lines'],
        );

        return 'Custom top up of '.$estimate['formatted']
            .($allocations === [] ? '' : ' · '.implode(', ', $allocations));
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
        return $this->settings->slider();
    }

    /**
     * The smallest and largest top up a client may pay.
     *
     * @return array{min: float, max: float}
     */
    public function limits(): array
    {
        return $this->settings->limits();
    }

    /**
     * The currency amounts are quoted in.
     */
    public function currency(): string
    {
        return (string) config('billing.currency', 'GHS');
    }

    /**
     * Hold an amount inside the top up limits, to the cent.
     */
    public function clamp(float $amount): float
    {
        $limits = $this->limits();

        return round(min(max($amount, $limits['min']), $limits['max']), 2);
    }

    /**
     * The selling rate of one service, or 0 when the platform does not sell it.
     */
    public function unitPrice(string $service): float
    {
        return $this->settings->rate($service);
    }

    /**
     * How many whole units of the service the amount covers.
     */
    protected function quantity(float $amount, float $unitPrice): int
    {
        if ($unitPrice <= 0.0) {
            return 0;
        }

        return (int) floor(round($amount / $unitPrice, 6));
    }

    /**
     * A price with its currency, for labels built on the server.
     */
    protected function money(float $amount): string
    {
        return $this->currency().' '.number_format($amount, 2);
    }
}
