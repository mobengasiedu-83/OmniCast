import type { BillingQuoteLine, BillingRate, BillingSlider } from '@/types';

/**
 * How many whole units of a service an amount covers.
 *
 * The division is rounded to six decimals first so a float artifact such as
 * 150 ÷ 0.04 = 3749.9999 still counts as 3750 units, then rounded down: a
 * client is never quoted a unit the money does not quite cover. The server
 * applies the same arithmetic in `App\Services\Billing\CreditEstimator`.
 */
export function quantityFor(amount: number, unitPrice: number): number {
    if (unitPrice <= 0) {
        return 0;
    }

    return Math.floor(Number((amount / unitPrice).toFixed(6)));
}

/**
 * What the amount buys on every channel, in the order the server quotes them.
 */
export function quoteFor(
    amount: number,
    rates: BillingRate[],
): BillingQuoteLine[] {
    return rates.map((rate) => {
        const quantity = quantityFor(amount, rate.unit_price);

        return {
            ...rate,
            quantity,
            formatted: quantity.toLocaleString('en-GH'),
        };
    });
}

/**
 * Hold an amount inside the slider, which sits within the top up limits.
 */
export function clampToSlider(amount: number, slider: BillingSlider): number {
    return Math.min(Math.max(amount, slider.min), slider.max);
}
