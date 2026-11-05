import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, Clock, Smartphone } from 'lucide-react';
import { useState, type FormEvent } from 'react';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { urls } from '@/lib/urls';
import type {
    MobileMoneyProviderOption,
    WalletMobileMoneyPayment,
    WalletPackage,
} from '@/types';

type PageProps = {
    client: { id: number; name: string } | null;
    currency: string;
    packages: WalletPackage[];
    providers: MobileMoneyProviderOption[];
    selected: string | null;
    limits: { min: number; max: number };
    payment: WalletMobileMoneyPayment | null;
    gateway: { driver: string; live: boolean; mobile_money: boolean };
};

function money(value: number, currency: string): string {
    return `${currency} ${value.toLocaleString('en-GH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

export default function WalletMobileMoney({
    client,
    currency,
    packages,
    providers,
    selected,
    limits,
    payment,
    gateway,
}: PageProps) {
    const form = useForm<{
        package: string;
        phone: string;
        provider: string;
        client_id: number | null;
    }>({
        package: selected ?? packages[0]?.key ?? '',
        phone: '',
        provider: providers[0]?.value ?? '',
        client_id: client?.id ?? null,
    });

    const otp = useForm<{ otp: string }>({ otp: '' });

    const [checking, setChecking] = useState(false);

    const chosen = packages.find((pkg) => pkg.key === form.data.package);

    /**
     * Ghanaian numbers carry their network in the prefix, so the provider is
     * chosen for the client as soon as the number is recognisable.
     */
    const detectProvider = (value: string): void => {
        const digits = value.replace(/\D+/g, '').replace(/^233/, '0');
        const prefix = digits.slice(0, 3);
        const match = providers.find((option) =>
            option.prefixes.includes(prefix),
        );

        if (match !== undefined) {
            form.setData('provider', match.value);
        }
    };

    const startCharge = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        form.post(urls.wallet.mobileMoney.store, { preserveScroll: true });
    };

    const submitOtp = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        if (payment !== null) {
            otp.post(urls.wallet.mobileMoney.otp(payment.id), {
                preserveScroll: true,
            });
        }
    };

    const checkStatus = (): void => {
        if (payment !== null) {
            setChecking(true);

            router.post(
                urls.wallet.mobileMoney.refresh(payment.id),
                {},
                {
                    preserveScroll: true,
                    onFinish: () => setChecking(false),
                },
            );
        }
    };

    const closeCheckout = (): void => {
        if (payment !== null) {
            router.get(urls.wallet.cancelCheckout(payment.id));
        }
    };

    return (
        <>
            <Head title="Mobile money" />

            <div className="flex flex-col gap-4">
                <PageHeader
                    icon={Smartphone}
                    title="Pay by mobile money"
                    description={`Request ${currency} from a wallet and approve it on the phone. The wallet is credited once the network confirms.`}
                    badge={
                        <Badge
                            variant={gateway.mobile_money ? 'success' : 'muted'}
                        >
                            {gateway.mobile_money
                                ? `${gateway.driver} mobile money`
                                : `${gateway.driver} gateway`}
                        </Badge>
                    }
                    actions={
                        <Button variant="outline" asChild>
                            <Link href={urls.wallet.index}>
                                <ArrowLeft className="size-4" />
                                Wallet
                            </Link>
                        </Button>
                    }
                />

                {!gateway.mobile_money && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Not available on this gateway</CardTitle>
                            <CardDescription>
                                {`The ${gateway.driver} gateway cannot prompt a mobile money wallet from this form. Pay on the hosted checkout from the wallet page instead.`}
                            </CardDescription>
                        </CardHeader>
                    </Card>
                )}

                {gateway.mobile_money && payment === null && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Which wallet?</CardTitle>
                            <CardDescription>
                                Pick the package to buy, then the mobile money
                                number that will approve the charge.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={startCharge} className="grid gap-4">
                                <div className="grid gap-2">
                                    <Label>Package</Label>
                                    <div className="grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                                        {packages.map((pkg) => (
                                            <button
                                                key={pkg.key}
                                                type="button"
                                                onClick={() =>
                                                    form.setData(
                                                        'package',
                                                        pkg.key,
                                                    )
                                                }
                                                className={
                                                    form.data.package ===
                                                    pkg.key
                                                        ? 'border-brand bg-brand/5 rounded-xl border p-3 text-left'
                                                        : 'hover:border-brand/40 rounded-xl border p-3 text-left'
                                                }
                                            >
                                                <p className="text-sm font-semibold">
                                                    {pkg.name}
                                                </p>
                                                <p className="text-muted-foreground text-xs tabular-nums">
                                                    {money(pkg.price, currency)}
                                                </p>
                                            </button>
                                        ))}
                                    </div>
                                    <InputError message={form.errors.package} />
                                </div>

                                <div className="grid gap-3 sm:grid-cols-2">
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="phone">
                                            Mobile money number
                                        </Label>
                                        <Input
                                            id="phone"
                                            value={form.data.phone}
                                            onChange={(event) => {
                                                form.setData(
                                                    'phone',
                                                    event.target.value,
                                                );
                                                detectProvider(
                                                    event.target.value,
                                                );
                                            }}
                                            placeholder="024 421 5189"
                                            inputMode="tel"
                                            autoComplete="tel"
                                        />
                                        <InputError message={form.errors.phone} />
                                    </div>

                                    <div className="grid gap-1.5">
                                        <Label htmlFor="provider">Network</Label>
                                        <Select
                                            value={form.data.provider}
                                            onValueChange={(value) =>
                                                form.setData('provider', value)
                                            }
                                        >
                                            <SelectTrigger
                                                id="provider"
                                                className="w-full"
                                            >
                                                <SelectValue placeholder="Select a network" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {providers.map((option) => (
                                                    <SelectItem
                                                        key={option.value}
                                                        value={option.value}
                                                    >
                                                        {option.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError message={form.errors.provider} />
                                    </div>
                                </div>

                                <div className="bg-muted/40 flex flex-wrap items-center justify-between gap-3 rounded-xl p-3">
                                    <div>
                                        <p className="text-sm font-medium">
                                            {chosen
                                                ? `${chosen.name} · ${money(chosen.price, currency)}`
                                                : 'Choose a package'}
                                        </p>
                                        <p className="text-muted-foreground text-xs">
                                            Top ups run from{' '}
                                            {money(limits.min, currency)} to{' '}
                                            {money(limits.max, currency)}.
                                        </p>
                                    </div>
                                    <Button
                                        type="submit"
                                        disabled={
                                            form.processing ||
                                            form.data.phone === ''
                                        }
                                    >
                                        <Smartphone className="size-4" />
                                        {form.processing
                                            ? 'Prompting the wallet…'
                                            : 'Send payment request'}
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                )}

                {payment !== null && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Clock className="text-brand size-4" />
                                {payment.requires_otp
                                    ? 'Enter the code from the wallet'
                                    : 'Approve the payment on the phone'}
                            </CardTitle>
                            <CardDescription>
                                {payment.message ??
                                    'The wallet has not answered yet.'}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <dl className="grid gap-3 text-sm sm:grid-cols-2 xl:grid-cols-3">
                                <Row label="Package" value={payment.description} />
                                <Row
                                    label="Amount"
                                    value={money(
                                        payment.amount,
                                        payment.currency,
                                    )}
                                />
                                <Row label="Wallet" value={payment.phone ?? '—'} />
                                <Row
                                    label="Network"
                                    value={payment.provider_label ?? '—'}
                                />
                                <Row label="Reference" value={payment.reference} mono />
                                <Row label="Status" value={payment.status_label} />
                            </dl>

                            {payment.failure_reason !== null && (
                                <p className="bg-destructive/10 text-destructive rounded-lg p-3 text-sm">
                                    {payment.failure_reason}
                                </p>
                            )}

                            {payment.requires_otp && (
                                <form
                                    onSubmit={submitOtp}
                                    className="grid gap-2 sm:max-w-xs"
                                >
                                    <Label htmlFor="otp">
                                        One time password
                                    </Label>
                                    <Input
                                        id="otp"
                                        value={otp.data.otp}
                                        onChange={(event) =>
                                            otp.setData(
                                                'otp',
                                                event.target.value.replace(
                                                    /\D+/g,
                                                    '',
                                                ),
                                            )
                                        }
                                        inputMode="numeric"
                                        autoComplete="one-time-code"
                                        maxLength={6}
                                        placeholder="123456"
                                    />
                                    <InputError message={otp.errors.otp} />
                                    <Button
                                        type="submit"
                                        disabled={
                                            otp.processing ||
                                            otp.data.otp.length !== 6
                                        }
                                    >
                                        <CheckCircle2 className="size-4" />
                                        {otp.processing
                                            ? 'Checking the code…'
                                            : 'Confirm code'}
                                    </Button>
                                </form>
                            )}

                            <div className="flex flex-wrap gap-2">
                                {payment.awaits_approval && (
                                    <Button
                                        type="button"
                                        onClick={checkStatus}
                                        disabled={checking}
                                    >
                                        <Clock className="size-4" />
                                        {checking
                                            ? 'Asking the wallet…'
                                            : 'I approved it — check again'}
                                    </Button>
                                )}

                                {payment.is_open ? (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={closeCheckout}
                                    >
                                        Close checkout
                                    </Button>
                                ) : (
                                    <Button type="button" variant="outline" asChild>
                                        <Link href={urls.wallet.mobileMoney.create}>
                                            Try again
                                        </Link>
                                    </Button>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

/**
 * One labelled value in the charge summary.
 */
function Row({
    label,
    value,
    mono = false,
}: {
    label: string;
    value: string;
    mono?: boolean;
}) {
    return (
        <div className="grid gap-0.5">
            <dt className="text-muted-foreground text-xs">{label}</dt>
            <dd className={mono ? 'font-mono text-xs' : 'font-medium'}>
                {value}
            </dd>
        </div>
    );
}

WalletMobileMoney.layout = {
    breadcrumbs: [
        {
            title: 'Wallet',
            href: urls.wallet.index,
        },
        {
            title: 'Mobile money',
            href: urls.wallet.mobileMoney.create,
        },
    ],
};

