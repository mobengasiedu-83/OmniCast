import { useState } from 'react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { clampToSlider, quoteFor } from '@/lib/pricing';
import { cn } from '@/lib/utils';
import type { BillingRate, BillingSlider } from '@/types';

type Props = {
    currency: string;
    rates: BillingRate[];
    slider: BillingSlider;
    limits: { min: number; max: number };
    value: number;
    onChange: (amount: number) => void;
    disabled?: boolean;
    id?: string;
    className?: string;
};

function money(value: number, currency: string): string {
    return `${currency} ${value.toLocaleString('en-GH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

/**
 * The "choose your own amount" control.
 *
 * The client drags the slider (or types an exact amount) and the card beside it
 * says what that money buys in SMS, voice minutes and emails. The quantities
 * come from the same rates the server quotes a top up from, so the preview can
 * follow the slider without a round trip to the platform.
 */
export default function AmountSlider({
    currency,
    rates,
    slider,
    limits,
    value,
    onChange,
    disabled = false,
    id = 'amount',
    className,
}: Props) {
    const [draft, setDraft] = useState<string | null>(null);

    const typed = draft === null ? Number.NaN : Number(draft);
    const preview =
        Number.isFinite(typed) && draft?.trim() !== '' ? typed : value;

    const quote = quoteFor(clampToSlider(preview, slider), rates);

    const commit = (): void => {
        if (draft === null) {
            return;
        }

        if (Number.isFinite(typed) && typed > 0) {
            onChange(Math.min(Math.max(typed, limits.min), limits.max));
        }

        setDraft(null);
    };

    return (
        <div className={cn('grid gap-4', className)}>
            <div className="flex flex-wrap items-end justify-between gap-3">
                <div className="space-y-1">
                    <Label
                        htmlFor={`${id}-range`}
                        className="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                    >
                        Amount to pay
                    </Label>
                    <p className="text-3xl font-semibold tracking-tight tabular-nums">
                        {money(preview, currency)}
                    </p>
                </div>

                <div className="grid w-36 gap-1.5">
                    <Label
                        htmlFor={`${id}-input`}
                        className="text-xs text-muted-foreground"
                    >
                        Exact amount
                    </Label>
                    <Input
                        id={`${id}-input`}
                        type="number"
                        inputMode="decimal"
                        min={limits.min}
                        max={limits.max}
                        step="0.01"
                        value={draft ?? value.toFixed(2)}
                        disabled={disabled}
                        onFocus={() => setDraft(value.toFixed(2))}
                        onChange={(event) => setDraft(event.target.value)}
                        onBlur={commit}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter') {
                                event.preventDefault();
                                commit();
                            }
                        }}
                    />
                </div>
            </div>

            <input
                id={`${id}-range`}
                type="range"
                min={slider.min}
                max={slider.max}
                step={slider.step}
                value={clampToSlider(value, slider)}
                disabled={disabled}
                aria-label="Amount to pay"
                onChange={(event) => onChange(Number(event.target.value))}
                className="h-2 w-full cursor-pointer appearance-none rounded-full bg-muted accent-brand disabled:cursor-not-allowed disabled:opacity-50"
            />

            <div className="flex items-center justify-between text-xs text-muted-foreground tabular-nums">
                <span>{money(slider.min, currency)}</span>
                <span>{money(slider.max, currency)}</span>
            </div>

            <ul className="grid gap-2 sm:grid-cols-3">
                {quote.map((line) => (
                    <li
                        key={line.service}
                        className="rounded-xl border border-border/70 bg-muted/30 p-3"
                    >
                        <p className="text-xs font-medium text-muted-foreground">
                            {line.label}
                        </p>
                        <p className="text-xl font-semibold tracking-tight tabular-nums">
                            {line.formatted}
                        </p>
                        <p className="mt-1 text-[11px] leading-snug text-muted-foreground">
                            {line.price_formatted} · {line.note}
                        </p>
                    </li>
                ))}
            </ul>
        </div>
    );
}
