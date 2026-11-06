import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    BadgeCheck,
    Mail,
    MessageSquare,
    Phone,
    ReceiptText,
    Wallet as WalletIcon,
    type LucideIcon,
} from 'lucide-react';
import { useState } from 'react';
import AmountSlider from '@/components/amount-slider';
import { Button } from '@/components/ui/button';
import { urls } from '@/lib/urls';
import { cn } from '@/lib/utils';
import { dashboard, home } from '@/routes';
import { create as clientSignup } from '@/routes/client-registration';
import type { BillingRate, BillingSlider, WalletPackage } from '@/types';

type PageProps = {
    currency: string;
    packages: WalletPackage[];
    limits: { min: number; max: number };
    rates: BillingRate[];
    slider: BillingSlider;
    dynamic_pricing: boolean;
};

/**
 * The glyph shown beside each allocation on a package card.
 */
const CHANNEL_ICONS: Record<string, LucideIcon> = {
    sms: MessageSquare,
    voice: Phone,
    email: Mail,
};

function money(value: number, currency: string): string {
    return `${currency} ${value.toLocaleString('en-GH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

/**
 * How a service is named when a rate is quoted in a sentence ("GHS 0.04/SMS").
 */
const RATE_LABELS: Record<string, string> = {
    sms: 'SMS',
    voice: 'Voice minute',
    email: 'Email',
};

/**
 * The published rates written into the capacity footnote, read from the same
 * rates the slider quotes so the two never disagree after an admin edit.
 */
function rateSummary(rates: BillingRate[], currency: string): string {
    const parts = rates.map(
        (rate) =>
            `${money(rate.unit_price, currency)}/${RATE_LABELS[rate.service] ?? rate.label}`,
    );

    if (parts.length <= 1) {
        return parts.join('');
    }

    return `${parts.slice(0, -1).join(', ')} and ${parts[parts.length - 1]}`;
}

export default function Pricing({
    currency,
    packages,
    limits,
    rates,
    slider,
    dynamic_pricing,
}: PageProps) {
    const { auth } = usePage().props;
    const [customAmount, setCustomAmount] = useState(slider.default);

    const primaryHref = auth.user ? dashboard() : clientSignup();
    const primaryLabel = auth.user ? 'Open dashboard' : 'Create an account';
    const customHref = auth.user
        ? `${urls.wallet.index}?amount=${customAmount.toFixed(2)}`
        : primaryHref;

    return (
        <>
            <Head title="Pricing" />

            <section className="py-10 lg:py-14">
                <div className="max-w-2xl animate-fade-in-up space-y-5">
                    <span className="inline-flex items-center gap-2 rounded-full bg-brand-soft px-3 py-1 text-xs font-medium text-accent-foreground ring-1 ring-brand/20">
                        <span className="flex size-1.5 rounded-full bg-brand" />
                        Price list · {currency} wallet
                    </span>

                    <h1 className="text-4xl leading-[1.05] font-semibold tracking-tight text-balance sm:text-5xl">
                        One price, your choice of{' '}
                        <span className="brand-text">SMS, voice or email</span>.
                    </h1>

                    <p className="text-base leading-relaxed text-muted-foreground">
                        Every package credits your wallet with the amount you
                        pay. Spend that credit on SMS, voice minutes or emails —
                        each package shows how far the same money stretches on
                        every channel.
                    </p>

                    <div className="flex flex-wrap items-center gap-3">
                        <Button size="lg" asChild>
                            <Link href={primaryHref}>
                                {primaryLabel}
                                <ArrowRight />
                            </Link>
                        </Button>
                        <Button size="lg" variant="outline" asChild>
                            <Link href={home()}>Back to the overview</Link>
                        </Button>
                    </div>
                </div>
            </section>

            <div className="mb-5 max-w-2xl space-y-2">
                <h2 className="text-2xl font-semibold tracking-tight">
                    Choose your top-up
                </h2>
                <p className="text-sm leading-relaxed text-muted-foreground">
                    Buy any amount, any time. Your new purchase adds to your
                    existing balance. No subscriptions or downgrades.
                </p>
            </div>

            <section
                aria-label="Wallet packages"
                className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
            >
                {packages.map((pkg, index) => (
                    <article
                        key={pkg.key}
                        style={{ animationDelay: `${index * 70}ms` }}
                        className={cn(
                            'relative flex card-lift animate-fade-in-up flex-col rounded-2xl border bg-card p-6 shadow-sm',
                            pkg.popular
                                ? 'border-brand/40 ring-1 ring-brand/20'
                                : 'border-border/70',
                        )}
                    >
                        <div className="flex items-start justify-between gap-2">
                            <h2 className="text-lg font-semibold">
                                {pkg.name}
                            </h2>
                            {pkg.popular && (
                                <span className="rounded-full brand-gradient px-2 py-0.5 text-[10px] font-semibold tracking-wide text-white uppercase">
                                    Popular
                                </span>
                            )}
                        </div>

                        <p className="mt-1 min-h-10 text-sm leading-relaxed text-muted-foreground">
                            {pkg.tagline}
                        </p>

                        <p className="mt-4 flex items-baseline gap-1.5">
                            <span className="text-xs font-semibold tracking-[0.14em] text-muted-foreground uppercase">
                                {currency}
                            </span>
                            <span className="text-3xl font-semibold tracking-tight">
                                {pkg.price.toLocaleString('en-GH', {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2,
                                })}
                            </span>
                        </p>
                        <p className="text-xs text-muted-foreground">
                            wallet credit, one payment
                        </p>

                        <ul className="mt-5 space-y-2.5 border-t border-border/60 pt-4">
                            {pkg.units.map((unit) => {
                                const Icon =
                                    CHANNEL_ICONS[unit.service] ??
                                    MessageSquare;

                                return (
                                    <li
                                        key={unit.service}
                                        className="flex items-center gap-2.5 text-sm"
                                    >
                                        <span className="flex size-7 shrink-0 items-center justify-center rounded-lg brand-gradient-soft text-brand ring-1 ring-brand/15">
                                            <Icon className="size-3.5" />
                                        </span>
                                        <span className="font-medium">
                                            {unit.formatted}
                                        </span>
                                        <span className="text-muted-foreground">
                                            {unit.label}
                                        </span>
                                    </li>
                                );
                            })}
                        </ul>

                        <Button
                            className="mt-6 w-full"
                            variant={pkg.popular ? 'default' : 'outline'}
                            asChild
                        >
                            <Link href={primaryHref}>
                                {auth.user ? 'Fund wallet' : 'Get started'}
                            </Link>
                        </Button>
                    </article>
                ))}
            </section>

            {dynamic_pricing && rates.length > 0 && (
                <section
                    aria-label="Choose your own amount"
                    className="mt-6 rounded-2xl border border-border/70 bg-card p-6"
                >
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div className="space-y-1">
                            <h2 className="font-semibold">
                                Choose your own amount
                            </h2>
                            <p className="max-w-xl text-sm leading-relaxed text-muted-foreground">
                                Drag to any amount from{' '}
                                {money(slider.min, currency)} to{' '}
                                {money(slider.max, currency)} and see what it
                                buys in SMS, voice minutes and emails before you
                                pay.
                            </p>
                        </div>
                        <Button variant="outline" asChild>
                            <Link href={customHref}>
                                {auth.user
                                    ? 'Top up this amount'
                                    : 'Get started'}
                                <ArrowRight />
                            </Link>
                        </Button>
                    </div>

                    <AmountSlider
                        id="pricing-amount"
                        className="mt-5"
                        currency={currency}
                        rates={rates}
                        slider={slider}
                        limits={limits}
                        value={customAmount}
                        onChange={setCustomAmount}
                    />
                </section>
            )}

            <p className="mt-4 text-xs leading-relaxed text-muted-foreground">
                Capacity figures use indicative rates of{' '}
                {rateSummary(rates, currency)}. A package buys its allocation on
                any one channel: the three lines are what the same money is
                worth in SMS, voice minutes and emails. Voice billing depends on
                billable call duration and rounding rules. All channels share
                the same {currency} balance.
            </p>

            <section className="mt-12 grid gap-4 sm:grid-cols-3">
                {[
                    {
                        icon: WalletIcon,
                        title: 'One balance, three channels',
                        copy: 'The credit you buy covers SMS, voice and email. You pick the channel when you build the campaign, not when you pay.',
                    },
                    {
                        icon: ReceiptText,
                        title: 'Reserved, then captured',
                        copy: 'Submitting a campaign reserves its cost. The credit is only spent when the campaign actually launches.',
                    },
                    {
                        icon: BadgeCheck,
                        title: 'Every send itemised',
                        copy: 'Wallet statements, delivery reports and invoices share one reference per campaign, down to the recipient.',
                    },
                ].map((feature, index) => (
                    <div
                        key={feature.title}
                        style={{ animationDelay: `${index * 70}ms` }}
                        className="card-lift animate-fade-in-up rounded-xl border border-border/70 bg-card p-5 shadow-sm"
                    >
                        <span className="mb-3 flex size-10 items-center justify-center rounded-xl brand-gradient-soft text-brand ring-1 ring-brand/15">
                            <feature.icon className="size-5" />
                        </span>
                        <p className="font-medium">{feature.title}</p>
                        <p className="mt-1 text-sm leading-relaxed text-muted-foreground">
                            {feature.copy}
                        </p>
                    </div>
                ))}
            </section>

            <section className="mt-6 flex flex-col gap-4 rounded-2xl border border-border/70 bg-card p-6 sm:flex-row sm:items-center sm:justify-between">
                <div className="space-y-1">
                    <p className="font-semibold">Need a different amount?</p>
                    <p className="text-sm leading-relaxed text-muted-foreground">
                        Top up anything from {money(limits.min, currency)} to{' '}
                        {money(limits.max, currency)} once you are inside. The
                        credit lands as soon as the gateway confirms the
                        payment.
                    </p>
                </div>
                <Button variant="outline" asChild>
                    <Link href={primaryHref}>
                        {auth.user ? 'Fund the wallet' : 'Get started'}
                        <ArrowRight />
                    </Link>
                </Button>
            </section>
        </>
    );
}
