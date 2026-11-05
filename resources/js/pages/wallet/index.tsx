import { Head, router } from '@inertiajs/react';
import {
    BadgeCheck,
    Banknote,
    CreditCard,
    ExternalLink,
    Lock,
    ReceiptText,
    Smartphone,
    Sparkles,
    Wallet as WalletIcon,
    WalletCards,
} from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { useConfirm } from '@/components/confirm-dialog';
import ClientFilter from '@/components/client-filter';
import EmptyState from '@/components/empty-state';
import PageHeader from '@/components/page-header';
import Pagination from '@/components/pagination';
import StatCard from '@/components/stat-card';
import StatusBadge from '@/components/status-badge';
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
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { urls } from '@/lib/urls';
import type {
    ClientOption,
    Paginated,
    WalletInvoiceRow,
    WalletPackage,
    WalletPaymentRow,
    WalletReservationRow,
    WalletSummary,
    WalletTransactionRow,
} from '@/types';

type PageProps = {
    wallet: WalletSummary | null;
    transactions: Paginated<WalletTransactionRow> | null;
    reservations: WalletReservationRow[];
    client: { id: number; name: string } | null;
    clients: ClientOption[];
    currency: string;
    packages: WalletPackage[];
    limits: { min: number; max: number };
    gateway: { driver: string; live: boolean; mobile_money: boolean };
    payments: WalletPaymentRow[];
    invoices: WalletInvoiceRow[];
    can: { checkout: boolean; manual_credit: boolean };
};

function money(value: number, currency: string): string {
    return `${currency} ${value.toLocaleString('en-GH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

function moment(value: string | null): string {
    return value
        ? new Date(value).toLocaleString(undefined, {
              dateStyle: 'medium',
              timeStyle: 'short',
          })
        : '—';
}

export default function WalletIndex({
    wallet,
    transactions,
    reservations,
    client,
    clients,
    currency,
    packages,
    limits,
    gateway,
    payments,
    invoices,
    can,
}: PageProps) {
    const [amount, setAmount] = useState('');
    const [description, setDescription] = useState('');
    const [reference, setReference] = useState('');
    const [starting, setStarting] = useState<string | null>(null);
    const confirm = useConfirm();

    const walletCurrency = wallet?.currency ?? currency;

    const startCheckout = (packageKey: string): void => {
        setStarting(packageKey);

        router.post(
            urls.wallet.checkout,
            { package: packageKey, client_id: client?.id ?? null },
            {
                preserveScroll: true,
                onFinish: () => setStarting(null),
            },
        );
    };

    const startMobileMoney = (packageKey: string): void => {
        router.get(urls.wallet.mobileMoney.create, {
            package: packageKey,
            client_id: client?.id ?? null,
        });
    };

    const creditManually = async (
        event: FormEvent<HTMLFormElement>,
    ): Promise<void> => {
        event.preventDefault();

        const amountLabel =
            amount === '' ? 'this amount' : `${walletCurrency} ${amount}`;

        const confirmed = await confirm({
            title: `Credit ${amountLabel} to the wallet?`,
            description:
                'The balance increases as soon as you confirm. Mistakes have to be corrected with a separate adjustment.',
            confirmLabel: 'Credit wallet',
        });

        if (!confirmed) {
            return;
        }

        router.post(
            urls.wallet.topUp,
            {
                amount,
                description,
                reference,
                client_id: client?.id ?? null,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setAmount('');
                    setDescription('');
                    setReference('');
                },
            },
        );
    };

    return (
        <>
            <Head title="Wallet" />

            <div className="flex flex-col gap-4">
                <PageHeader
                    icon={WalletIcon}
                    title={client === null ? 'Wallet' : `${client.name} wallet`}
                    description={`Pre-paid credit in ${currency}. Top up by card and every campaign is reserved against this balance.`}
                    badge={
                        <Badge variant={gateway.live ? 'success' : 'muted'}>
                            {gateway.live
                                ? 'Live card payments'
                                : `${gateway.driver} gateway`}
                        </Badge>
                    }
                    actions={
                        <ClientFilter
                            clients={clients}
                            value={client?.id ?? null}
                        />
                    }
                />

                {wallet === null && (
                    <Card>
                        <EmptyState
                            icon={WalletIcon}
                            title="Select a client account"
                            description="Choose the client whose wallet you want to fund. Platform operators can only top up one account at a time."
                        />
                    </Card>
                )}

                {wallet !== null && (
                    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <StatCard
                            title="Available"
                            value={money(wallet.available, wallet.currency)}
                            description="What campaigns can spend right now"
                            icon={WalletCards}
                            tone="brand"
                        />
                        <StatCard
                            title="Balance"
                            value={money(wallet.balance, wallet.currency)}
                            description="Credit added less everything spent"
                            icon={Banknote}
                            tone="success"
                        />
                        <StatCard
                            title="Reserved"
                            value={money(wallet.reserved, wallet.currency)}
                            description={`${reservations.length} campaign${reservations.length === 1 ? '' : 's'} holding credit`}
                            icon={Lock}
                            tone="warning"
                        />
                        <StatCard
                            title="Low balance trigger"
                            value={money(
                                wallet.low_balance_threshold,
                                wallet.currency,
                            )}
                            description={
                                wallet.is_low
                                    ? 'Balance is below the trigger'
                                    : 'Balance is healthy'
                            }
                            icon={Sparkles}
                            tone={wallet.is_low ? 'danger' : 'neutral'}
                        />
                    </div>
                )}


                {wallet !== null && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <CreditCard className="text-brand size-4" />
                                Top up the wallet
                            </CardTitle>
                            <CardDescription>
                                Pick a package and pay on the gateway&apos;s
                                secure checkout page, or have us prompt your
                                mobile money wallet right here. Credit lands
                                once the charge is confirmed
                                {limits.min > 0
                                    ? ` · minimum ${money(limits.min, currency)}`
                                    : ''}
                                .
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {packages.length === 0 ? (
                                <EmptyState
                                    icon={CreditCard}
                                    title="No packages configured"
                                    description="The wallet catalogue is empty. Add packages to config/billing.php."
                                />
                            ) : (
                                <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                                    {packages.map((pkg) => (
                                        <PackageCard
                                            key={pkg.key}
                                            pkg={pkg}
                                            currency={currency}
                                            disabled={
                                                !can.checkout ||
                                                starting !== null
                                            }
                                            starting={starting === pkg.key}
                                            mobileMoney={gateway.mobile_money}
                                            onSelect={startCheckout}
                                            onSelectMobileMoney={
                                                startMobileMoney
                                            }
                                        />
                                    ))}
                                </div>
                            )}

                            {!can.checkout && (
                                <p className="text-muted-foreground text-xs">
                                    Your role can see the wallet but not fund
                                    it.
                                </p>
                            )}
                        </CardContent>
                    </Card>
                )}


                <div className="grid gap-4 xl:grid-cols-2">
                    <Card className="overflow-hidden">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <CreditCard className="text-brand size-4" />
                                Recent payments
                            </CardTitle>
                            <CardDescription>
                                Card charges raised against this wallet.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="px-0">
                            {payments.length === 0 ? (
                                <EmptyState
                                    icon={CreditCard}
                                    title="No payments yet"
                                    description="Card charges appear here as soon as a checkout is started."
                                />
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="pl-6">
                                                Reference
                                            </TableHead>
                                            <TableHead>Amount</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead className="pr-6">
                                                Started
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {payments.map((payment) => (
                                            <TableRow key={payment.id}>
                                                <TableCell className="pl-6 font-mono text-xs">
                                                    {payment.reference}
                                                </TableCell>
                                                <TableCell className="tabular-nums">
                                                    {money(
                                                        payment.amount,
                                                        payment.currency,
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    <StatusBadge
                                                        status={payment.status}
                                                    />
                                                </TableCell>
                                                <TableCell className="text-muted-foreground pr-6 text-xs">
                                                    {moment(
                                                        payment.created_at,
                                                    )}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </CardContent>
                    </Card>



                    <Card className="overflow-hidden">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <ReceiptText className="text-brand size-4" />
                                Recent invoices
                            </CardTitle>
                            <CardDescription>
                                Tax invoices issued for settled payments.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="px-0">
                            {invoices.length === 0 ? (
                                <EmptyState
                                    icon={ReceiptText}
                                    title="No invoices yet"
                                    description="An invoice is raised automatically when a payment settles."
                                />
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="pl-6">
                                                Number
                                            </TableHead>
                                            <TableHead>Total</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead className="pr-6">
                                                Issued
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {invoices.map((invoice) => (
                                            <TableRow key={invoice.id}>
                                                <TableCell className="pl-6 font-mono text-xs">
                                                    {invoice.number}
                                                </TableCell>
                                                <TableCell className="tabular-nums">
                                                    {money(
                                                        invoice.total,
                                                        invoice.currency,
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    <StatusBadge
                                                        status={invoice.status}
                                                    />
                                                </TableCell>
                                                <TableCell className="text-muted-foreground pr-6 text-xs">
                                                    {moment(invoice.issued_at)}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-4 xl:grid-cols-2">
                    <Card className="overflow-hidden">
                        <CardHeader>
                            <CardTitle>Ledger</CardTitle>
                            <CardDescription>
                                Every credit, reservation and capture on this
                                wallet.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="px-0">
                            {transactions === null ||
                            transactions.data.length === 0 ? (
                                <EmptyState
                                    icon={WalletCards}
                                    title="No ledger entries"
                                    description="Top ups, reservations and captures are recorded here."
                                />
                            ) : (
                                <>
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead className="pl-6">
                                                    Movement
                                                </TableHead>
                                                <TableHead>Amount</TableHead>
                                                <TableHead>Balance</TableHead>
                                                <TableHead className="pr-6">
                                                    When
                                                </TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {transactions.data.map(
                                                (transaction) => (
                                                    <TableRow
                                                        key={transaction.id}
                                                    >
                                                        <TableCell className="pl-6">
                                                            <p className="font-medium">
                                                                {
                                                                    transaction.type_label
                                                                }
                                                            </p>
                                                            <p className="text-muted-foreground text-xs">
                                                                {transaction.description ??
                                                                    '—'}
                                                            </p>
                                                        </TableCell>
                                                        <TableCell
                                                            className={
                                                                transaction.increases_balance
                                                                    ? 'text-success tabular-nums'
                                                                    : 'text-danger tabular-nums'
                                                            }
                                                        >
                                                            {transaction.increases_balance
                                                                ? '+'
                                                                : '−'}
                                                            {money(
                                                                transaction.amount,
                                                                walletCurrency,
                                                            )}
                                                        </TableCell>
                                                        <TableCell className="tabular-nums">
                                                            {money(
                                                                transaction.balance_after,
                                                                walletCurrency,
                                                            )}
                                                        </TableCell>
                                                        <TableCell className="text-muted-foreground pr-6 text-xs">
                                                            {moment(
                                                                transaction.created_at,
                                                            )}
                                                        </TableCell>
                                                    </TableRow>
                                                ),
                                            )}
                                        </TableBody>
                                    </Table>
                                    <Pagination
                                        paginator={transactions}
                                        noun="entries"
                                    />
                                </>
                            )}
                        </CardContent>
                    </Card>


                    <Card className="overflow-hidden">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Lock className="text-brand size-4" />
                                Reserved by campaigns
                            </CardTitle>
                            <CardDescription>
                                Credit held while a campaign is scheduling.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="px-0">
                            {reservations.length === 0 ? (
                                <EmptyState
                                    icon={Lock}
                                    title="Nothing reserved"
                                    description="Campaigns reserve credit the moment they are submitted."
                                />
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="pl-6">
                                                Campaign
                                            </TableHead>
                                            <TableHead>Held</TableHead>
                                            <TableHead className="pr-6">
                                                Captured
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {reservations.map((reservation) => (
                                            <TableRow key={reservation.id}>
                                                <TableCell className="pl-6">
                                                    <p className="font-medium">
                                                        {reservation.campaign ??
                                                            'Campaign'}
                                                    </p>
                                                    <p className="text-muted-foreground font-mono text-xs">
                                                        {reservation.reference ??
                                                            '—'}
                                                    </p>
                                                </TableCell>
                                                <TableCell className="tabular-nums">
                                                    {money(
                                                        reservation.amount,
                                                        walletCurrency,
                                                    )}
                                                </TableCell>
                                                <TableCell className="pr-6 tabular-nums">
                                                    {money(
                                                        reservation.captured_amount,
                                                        walletCurrency,
                                                    )}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </CardContent>
                    </Card>
                </div>


                {wallet !== null && can.manual_credit && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Banknote className="text-brand size-4" />
                                Adjust the balance
                            </CardTitle>
                            <CardDescription>
                                Credit a wallet by hand for bank transfers,
                                refunds or goodwill. Bounded between{' '}
                                {money(limits.min, currency)} and{' '}
                                {money(limits.max, currency)}.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form
                                onSubmit={creditManually}
                                className="grid gap-3 md:grid-cols-2 xl:grid-cols-4"
                            >
                                <div className="grid gap-1.5">
                                    <Label htmlFor="amount">Amount</Label>
                                    <Input
                                        id="amount"
                                        type="number"
                                        min={limits.min}
                                        max={limits.max}
                                        step="0.01"
                                        value={amount}
                                        onChange={(event) =>
                                            setAmount(event.target.value)
                                        }
                                        required
                                    />
                                </div>
                                <div className="grid gap-1.5">
                                    <Label htmlFor="description">
                                        Description
                                    </Label>
                                    <Input
                                        id="description"
                                        value={description}
                                        onChange={(event) =>
                                            setDescription(event.target.value)
                                        }
                                        placeholder="Bank transfer ref 0091"
                                    />
                                </div>
                                <div className="grid gap-1.5">
                                    <Label htmlFor="reference">
                                        Reference
                                    </Label>
                                    <Input
                                        id="reference"
                                        value={reference}
                                        onChange={(event) =>
                                            setReference(event.target.value)
                                        }
                                        placeholder="Optional"
                                    />
                                </div>
                                <div className="flex items-end">
                                    <Button
                                        type="submit"
                                        className="w-full"
                                        disabled={amount === ''}
                                    >
                                        <BadgeCheck className="size-4" />
                                        Credit wallet
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}


function PackageCard({
    pkg,
    currency,
    disabled,
    starting,
    mobileMoney,
    onSelect,
    onSelectMobileMoney,
}: {
    pkg: WalletPackage;
    currency: string;
    disabled: boolean;
    starting: boolean;
    mobileMoney: boolean;
    onSelect: (key: string) => void;
    onSelectMobileMoney: (key: string) => void;
}) {
    return (
        <div
            className={
                pkg.popular
                    ? 'border-brand/40 bg-brand/5 relative flex flex-col gap-3 rounded-xl border p-4'
                    : 'relative flex flex-col gap-3 rounded-xl border p-4'
            }
        >
            {pkg.popular && (
                <Badge variant="success" className="absolute top-3 right-3">
                    Popular
                </Badge>
            )}

            <div className="space-y-1">
                <p className="text-sm font-semibold">{pkg.name}</p>
                <p className="text-2xl font-semibold tracking-tight tabular-nums">
                    {money(pkg.price, currency)}
                </p>
                {pkg.tagline !== '' && (
                    <p className="text-muted-foreground text-xs">
                        {pkg.tagline}
                    </p>
                )}
            </div>

            <ul className="text-muted-foreground space-y-1 text-xs">
                {pkg.units.map((unit) => (
                    <li key={unit.service} className="flex items-center gap-1.5">
                        <BadgeCheck className="text-success size-3.5" />
                        {unit.formatted} {unit.label}
                    </li>
                ))}
            </ul>

            <div className="mt-auto grid gap-2">
                <Button
                    type="button"
                    disabled={disabled}
                    onClick={() => onSelect(pkg.key)}
                >
                    <ExternalLink className="size-4" />
                    {starting ? 'Opening checkout…' : 'Pay now'}
                </Button>

                {mobileMoney && (
                    <Button
                        type="button"
                        variant="outline"
                        disabled={disabled}
                        onClick={() => onSelectMobileMoney(pkg.key)}
                    >
                        <Smartphone className="size-4" />
                        Mobile money
                    </Button>
                )}
            </div>
        </div>
    );
}

