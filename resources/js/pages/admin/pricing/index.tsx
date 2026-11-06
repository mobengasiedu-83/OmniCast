import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowRight, Save, SlidersHorizontal } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import AmountSlider from '@/components/amount-slider';
import FormField from '@/components/form-field';
import InputError from '@/components/input-error';
import PageHeader from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { urls } from '@/lib/urls';
import { pricing } from '@/routes';
import type { BillingRate, BillingSlider } from '@/types';

/**
 * The price list `config/billing.php` publishes, and the overrides stored in
 * platform settings.
 */
type PriceList = {
    dynamic_pricing: boolean;
    rates: Record<string, number>;
    limits: { min: number; max: number };
    slider: BillingSlider;
};

type Props = {
    currency: string;
    dynamic_pricing: boolean;
    rates: BillingRate[];
    limits: { min: number; max: number };
    slider: BillingSlider;
    defaults: PriceList;
    overrides: Record<string, string>;
};

/**
 * The label, unit and arithmetic note shown beside each rate.
 *
 * The admin screen lists every channel, including a rate the platform has set
 * to zero, so the fields do not shift when a channel is switched off.
 */
const CHANNELS: Record<string, { label: string; unit: string; note: string }> =
    {
        sms: {
            label: 'SMS',
            unit: 'per SMS part',
            note: 'One unit is 160 characters to one number',
        },
        voice: {
            label: 'Voice minutes',
            unit: 'per minute',
            note: 'One unit is a minute of the call to one number',
        },
        email: {
            label: 'Emails',
            unit: 'per email',
            note: 'One unit is one email to one address',
        },
    };

function money(value: number, currency: string): string {
    return `${currency} ${value.toLocaleString('en-GH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

export default function DynamicPricing({
    currency,
    dynamic_pricing,
    rates,
    limits,
    slider,
    defaults,
    overrides,
}: Props) {
    /**
     * A rate the platform is publishing today, falling back to the shipped
     * default for a channel that is sold at zero.
     */
    const published = (service: string): number =>
        rates.find((rate) => rate.service === service)?.unit_price ??
        defaults.rates[service] ??
        0;

    /**
     * A stored override, or the value the configuration ships.
     */
    const setting = (key: string, fallback: number): number =>
        Number(overrides[key] ?? fallback);

    const form = useForm({
        dynamic_pricing,
        rates: {
            sms: String(published('sms')),
            voice: String(published('voice')),
            email: String(published('email')),
        },
        limits: {
            min: String(setting('limits.min_top_up', limits.min)),
            max: String(setting('limits.max_top_up', limits.max)),
        },
        slider: {
            min: String(setting('slider.min', slider.min)),
            max: String(setting('slider.max', slider.max)),
            step: String(setting('slider.step', slider.step)),
            default: String(setting('slider.default', slider.default)),
        },
    });

    const [previewAmount, setPreviewAmount] = useState(
        Number(form.data.slider.default) || 0,
    );

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        form.put(urls.admin.pricing.update, { preserveScroll: true });
    };

    /**
     * What the client-facing slider shows for the rates typed above.
     */
    const previewRates: BillingRate[] = Object.entries(CHANNELS).map(
        ([service, channel]) => {
            const unitPrice = Number(
                (form.data.rates as Record<string, string>)[service] ?? 0,
            );

            return {
                service,
                label: channel.label,
                unit: channel.unit,
                note: channel.note,
                unit_price: unitPrice,
                price_formatted: money(unitPrice, currency),
            };
        },
    );

    const previewSlider: BillingSlider = {
        min: Number(form.data.slider.min) || 0,
        max: Math.max(
            Number(form.data.slider.min) || 0,
            Number(form.data.slider.max) || 0,
        ),
        step: Math.max(0.01, Number(form.data.slider.step) || 0.01),
        default: Number(form.data.slider.default) || 0,
    };

    /**
     * The top up limits as they will be saved, kept ordered while a field is
     * half typed so the preview slider cannot invert.
     */
    const previewLimits = {
        min: Number(form.data.limits.min) || 0,
        max: Math.max(
            Number(form.data.limits.min) || 0,
            Number(form.data.limits.max) || 0,
        ),
    };

    const overridden = (key: string): boolean => overrides[key] !== undefined;

    const errorFor = (key: string): string | undefined =>
        (form.errors as Record<string, string | undefined>)[key];

    return (
        <>
            <Head title="Dynamic pricing" />

            <form onSubmit={submit} className="grid gap-6">
                <PageHeader
                    icon={SlidersHorizontal}
                    title="Dynamic pricing"
                    description="Sell whatever amount a client names instead of a fixed bundle. With the switch on, the wallet, the price list and mobile money all show the amount slider; with it off they fall back to packages and a submitted amount is refused."
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <Button asChild variant="outline">
                                <Link href={pricing()}>
                                    View the price list
                                    <ArrowRight className="size-4" />
                                </Link>
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                <Save className="size-4" />
                                {form.processing ? 'Saving…' : 'Save changes'}
                            </Button>
                        </div>
                    }
                />

                <Card>
                    <CardHeader>
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div className="space-y-1">
                                <CardTitle>Amount slider</CardTitle>
                                <CardDescription>
                                    Clients pick any amount between the top up
                                    limits and see what it buys in SMS, voice
                                    minutes and emails before they pay.
                                </CardDescription>
                            </div>
                            <Badge
                                variant={
                                    form.data.dynamic_pricing
                                        ? 'success'
                                        : 'muted'
                                }
                            >
                                <span className="size-1.5 rounded-full bg-current" />
                                {form.data.dynamic_pricing
                                    ? 'On — clients choose an amount'
                                    : 'Off — packages only'}
                            </Badge>
                        </div>
                    </CardHeader>

                    <CardContent className="grid gap-2">
                        <label
                            htmlFor="dynamic-pricing"
                            className="flex cursor-pointer items-start gap-3 rounded-xl border border-border/70 p-3"
                        >
                            <Checkbox
                                id="dynamic-pricing"
                                className="mt-0.5"
                                checked={form.data.dynamic_pricing}
                                onCheckedChange={(checked) =>
                                    form.setData(
                                        'dynamic_pricing',
                                        checked === true,
                                    )
                                }
                            />
                            <span className="space-y-1">
                                <span className="block text-sm font-medium">
                                    Let clients name their own amount
                                </span>
                                <span className="block text-xs text-muted-foreground">
                                    The rates and slider bounds below stay saved
                                    while this is off, so switching it back on
                                    needs no re-entry. A checkout that submits
                                    an amount while it is off is rejected rather
                                    than rounded to a package.
                                </span>
                            </span>
                        </label>

                        <InputError message={form.errors.dynamic_pricing} />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <div className="flex flex-wrap items-start justify-between gap-2">
                            <div className="space-y-1">
                                <CardTitle>Rates</CardTitle>
                                <CardDescription>
                                    One unit is one message, one minute or one
                                    email to a single MSISDN. An SMS part is 160
                                    characters, so an 800 character message to
                                    10 numbers is 50 units.
                                </CardDescription>
                            </div>
                            <Badge variant="muted">{currency} per unit</Badge>
                        </div>
                    </CardHeader>

                    <CardContent className="grid gap-4 sm:grid-cols-3">
                        {Object.entries(CHANNELS).map(([service, channel]) => (
                            <div key={service} className="grid gap-1.5">
                                <div className="flex items-center justify-between gap-2">
                                    <Label htmlFor={`rate-${service}`}>
                                        {channel.label}
                                    </Label>
                                    {overridden(`rates.${service}`) && (
                                        <Badge variant="muted">Override</Badge>
                                    )}
                                </div>

                                <Input
                                    id={`rate-${service}`}
                                    type="number"
                                    inputMode="decimal"
                                    min="0.0001"
                                    step="0.0001"
                                    value={
                                        (
                                            form.data.rates as Record<
                                                string,
                                                string
                                            >
                                        )[service] ?? ''
                                    }
                                    onChange={(event) =>
                                        form.setData('rates', {
                                            ...form.data.rates,
                                            [service]: event.target.value,
                                        })
                                    }
                                />

                                <InputError
                                    message={
                                        (form.errors as Record<string, string>)[
                                            `rates.${service}`
                                        ]
                                    }
                                />

                                <p className="text-xs text-muted-foreground">
                                    {channel.unit} · {channel.note}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    Ships at{' '}
                                    {money(
                                        defaults.rates[service] ?? 0,
                                        currency,
                                    )}
                                </p>
                            </div>
                        ))}
                    </CardContent>
                </Card>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Top up limits</CardTitle>
                            <CardDescription>
                                The smallest and largest wallet top up. They
                                apply to packages and custom amounts alike, and
                                the slider is clamped inside them.
                            </CardDescription>
                        </CardHeader>

                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            <FormField
                                label="Smallest top up"
                                htmlFor="limits-min"
                                error={errorFor('limits.min')}
                            >
                                <Input
                                    id="limits-min"
                                    type="number"
                                    inputMode="decimal"
                                    min="0.01"
                                    step="0.01"
                                    value={form.data.limits.min}
                                    onChange={(event) =>
                                        form.setData('limits', {
                                            ...form.data.limits,
                                            min: event.target.value,
                                        })
                                    }
                                />
                            </FormField>

                            <FormField
                                label="Largest top up"
                                htmlFor="limits-max"
                                error={errorFor('limits.max')}
                            >
                                <Input
                                    id="limits-max"
                                    type="number"
                                    inputMode="decimal"
                                    min="0.01"
                                    step="0.01"
                                    value={form.data.limits.max}
                                    onChange={(event) =>
                                        form.setData('limits', {
                                            ...form.data.limits,
                                            max: event.target.value,
                                        })
                                    }
                                />
                            </FormField>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Slider bounds</CardTitle>
                            <CardDescription>
                                Where the handle starts and stops, how far one
                                drag moves it, and the amount it opens on.
                            </CardDescription>
                        </CardHeader>

                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            <FormField
                                label="Starts at"
                                htmlFor="slider-min"
                                error={errorFor('slider.min')}
                            >
                                <Input
                                    id="slider-min"
                                    type="number"
                                    inputMode="decimal"
                                    min="0"
                                    step="0.01"
                                    value={form.data.slider.min}
                                    onChange={(event) =>
                                        form.setData('slider', {
                                            ...form.data.slider,
                                            min: event.target.value,
                                        })
                                    }
                                />
                            </FormField>

                            <FormField
                                label="Ends at"
                                htmlFor="slider-max"
                                error={errorFor('slider.max')}
                            >
                                <Input
                                    id="slider-max"
                                    type="number"
                                    inputMode="decimal"
                                    min="0"
                                    step="0.01"
                                    value={form.data.slider.max}
                                    onChange={(event) =>
                                        form.setData('slider', {
                                            ...form.data.slider,
                                            max: event.target.value,
                                        })
                                    }
                                />
                            </FormField>

                            <FormField
                                label="Step"
                                htmlFor="slider-step"
                                error={errorFor('slider.step')}
                            >
                                <Input
                                    id="slider-step"
                                    type="number"
                                    inputMode="decimal"
                                    min="0.01"
                                    step="0.01"
                                    value={form.data.slider.step}
                                    onChange={(event) =>
                                        form.setData('slider', {
                                            ...form.data.slider,
                                            step: event.target.value,
                                        })
                                    }
                                />
                            </FormField>

                            <FormField
                                label="Opens on"
                                htmlFor="slider-default"
                                error={errorFor('slider.default')}
                            >
                                <Input
                                    id="slider-default"
                                    type="number"
                                    inputMode="decimal"
                                    min="0"
                                    step="0.01"
                                    value={form.data.slider.default}
                                    onChange={(event) =>
                                        form.setData('slider', {
                                            ...form.data.slider,
                                            default: event.target.value,
                                        })
                                    }
                                />
                            </FormField>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>What a client sees</CardTitle>
                        <CardDescription>
                            The same slider the wallet renders, drawn from the
                            values above. Nothing here is stored until you save.
                        </CardDescription>
                    </CardHeader>

                    <CardContent>
                        <AmountSlider
                            id="pricing-preview"
                            currency={currency}
                            rates={previewRates}
                            slider={previewSlider}
                            limits={previewLimits}
                            value={previewAmount}
                            onChange={setPreviewAmount}
                        />
                    </CardContent>
                </Card>

                <p className="text-xs leading-relaxed text-muted-foreground">
                    Every quantity is quoted per MSISDN: an SMS part is 160
                    characters, a voice unit is a minute of the call, and an
                    email unit is one message to one address. The rates here win
                    over `config/billing.php` wherever a price is quoted — the
                    public price list, the wallet, the checkout and the mobile
                    money form.
                </p>
            </form>
        </>
    );
}

DynamicPricing.layout = {
    breadcrumbs: [{ title: 'Dynamic pricing', href: urls.admin.pricing.index }],
};
