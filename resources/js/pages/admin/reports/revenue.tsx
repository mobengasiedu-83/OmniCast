import { Head, Link } from '@inertiajs/react';
import {
    ArrowUpRight,
    Banknote,
    Coins,
    Download,
    ReceiptText,
    TrendingUp,
    Wallet,
} from 'lucide-react';
import EmptyState from '@/components/empty-state';
import PageHeader from '@/components/page-header';
import ReportFilters from '@/components/report-filters';
import StatCard from '@/components/stat-card';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
    RevenueMonthlyPoint,
    RevenueProviderRow,
    RevenueReport,
    RevenueStatusRow,
    WalletEntryRow,
} from '@/types';

type PageProps = {
    filters: {
        from: string;
        to: string;
        client_id: number | null;
    };
    period: { label: string; days: number };
    totals: RevenueReport['totals'];
    paymentStatuses: RevenueStatusRow[];
    invoiceStatuses: RevenueStatusRow[];
    wallet: WalletEntryRow[];
    byProvider: RevenueProviderRow[];
    monthly: RevenueMonthlyPoint[];
    clients: ClientOption[];
    can: { export: boolean };
};

function money(value: number): string {
    return `GHS ${value.toLocaleString('en-GH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

/**
 * Revenue the gateway actually settled, plus the credit the platform is still
 * holding for its clients.
 *
 * Requested payments and issued invoices are reported next to the settled
 * figure instead of being added to it, so the headline number is only money the
 * ledger can prove.
 */
export default function AdminRevenueReport({
    filters,
    period,
    totals,
    paymentStatuses,
    invoiceStatuses,
    wallet,
    byProvider,
    monthly,
    clients,
    can,
}: PageProps) {
    const peakRevenue = Math.max(...monthly.map((point) => point.revenue), 1);

    return (
        <>
            <Head title="Revenue" />

            <div className="flex flex-col gap-4">
                <PageHeader
                    icon={Banknote}
                    title="Revenue"
                    description="Settled payments, invoices and wallet credit read straight from the billing ledger."
                    badge={
                        <span className="text-xs text-muted-foreground">
                            {period.label}
                        </span>
                    }
                    actions={
                        can.export ? (
                            <Button asChild variant="outline">
                                <Link
                                    href={`${urls.admin.reports.exports.index}?report=revenue&from=${filters.from}&to=${filters.to}`}
                                >
                                    <Download />
                                    Export
                                </Link>
                            </Button>
                        ) : undefined
                    }
                />

                <Card className="py-4">
                    <CardContent>
                        <ReportFilters
                            url={urls.admin.reports.revenue}
                            values={filters}
                            clients={clients}
                        />
                    </CardContent>
                </Card>

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        title="Revenue"
                        value={money(totals.revenue)}
                        description={`${totals.payments} settled payment(s)`}
                        icon={TrendingUp}
                        tone="success"
                    />
                    <StatCard
                        title="Invoiced"
                        value={money(totals.invoiced)}
                        description={`${totals.invoices} invoice(s) issued`}
                        icon={ReceiptText}
                        tone="brand"
                    />
                    <StatCard
                        title="Campaign spend"
                        value={money(totals.spend)}
                        description="Charged to client wallets"
                        icon={Coins}
                        tone="signal"
                    />
                    <StatCard
                        title="Wallet credit"
                        value={money(totals.balance)}
                        description={`${money(totals.reserved)} reserved`}
                        icon={Wallet}
                        tone="neutral"
                    />
                    <StatCard
                        title="Awaiting payment"
                        value={totals.pending_payments}
                        description="Requested but not settled"
                        icon={Banknote}
                        tone="warning"
                    />
                    <StatCard
                        title="Failed payments"
                        value={totals.failed_payments}
                        description="Declined by the gateway"
                        icon={Banknote}
                        tone={totals.failed_payments > 0 ? 'danger' : 'neutral'}
                    />
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Monthly revenue</CardTitle>
                            <CardDescription>
                                Settled revenue over the last six months.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-2.5">
                            {monthly.map((point) => (
                                <div
                                    key={point.month}
                                    className="flex items-center gap-3"
                                >
                                    <span className="w-16 text-xs text-muted-foreground">
                                        {point.label}
                                    </span>
                                    <div className="h-2 flex-1 overflow-hidden rounded-full bg-muted">
                                        <div
                                            className="h-full rounded-full bg-success"
                                            style={{
                                                width: `${(point.revenue / peakRevenue) * 100}%`,
                                            }}
                                        />
                                    </div>
                                    <span className="w-28 text-right text-xs tabular-nums">
                                        {money(point.revenue)}
                                    </span>
                                </div>
                            ))}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>By gateway</CardTitle>
                            <CardDescription>
                                Which collection route the settled money came
                                through.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="px-0">
                            {byProvider.length === 0 ? (
                                <EmptyState
                                    icon={Banknote}
                                    title="No settled payments"
                                    description="Nothing was collected inside this window."
                                />
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="pl-6">
                                                Gateway
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Payments
                                            </TableHead>
                                            <TableHead className="pr-6 text-right">
                                                Revenue
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {byProvider.map((row) => (
                                            <TableRow key={row.provider}>
                                                <TableCell className="pl-6 font-medium capitalize">
                                                    {row.provider}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {row.payments}
                                                </TableCell>
                                                <TableCell className="pr-6 text-right tabular-nums">
                                                    {money(row.revenue)}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardTitle>Payment statuses</CardTitle>
                            <CardDescription>
                                Every request raised inside the window.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="px-0">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="pl-6">
                                            Status
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Count
                                        </TableHead>
                                        <TableHead className="pr-6 text-right">
                                            Amount
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {paymentStatuses.map((row) => (
                                        <TableRow key={row.status}>
                                            <TableCell className="pl-6">
                                                {row.label}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {row.total}
                                            </TableCell>
                                            <TableCell className="pr-6 text-right tabular-nums">
                                                {money(row.amount)}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Invoice statuses</CardTitle>
                            <CardDescription>
                                Invoices issued inside the window.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="px-0">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="pl-6">
                                            Status
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Count
                                        </TableHead>
                                        <TableHead className="pr-6 text-right">
                                            Amount
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {invoiceStatuses.map((row) => (
                                        <TableRow key={row.status}>
                                            <TableCell className="pl-6">
                                                {row.label}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {row.total}
                                            </TableCell>
                                            <TableCell className="pr-6 text-right tabular-nums">
                                                {money(row.amount)}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Wallet movements</CardTitle>
                            <CardDescription>
                                What moved in and out of the wallets.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="px-0">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="pl-6">
                                            Type
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Entries
                                        </TableHead>
                                        <TableHead className="pr-6 text-right">
                                            Amount
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {wallet.map((row) => (
                                        <TableRow key={row.type}>
                                            <TableCell className="pl-6">
                                                {row.label}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {row.entries}
                                            </TableCell>
                                            <TableCell className="pr-6 text-right tabular-nums">
                                                {money(row.amount)}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                </div>

                <Card className="py-4">
                    <CardContent className="flex flex-wrap items-center justify-between gap-3">
                        <p className="text-xs text-muted-foreground">
                            A payment counts as revenue only once the gateway
                            settled it; requested and failed payments are shown
                            separately.
                        </p>
                        <Button variant="ghost" size="sm" asChild>
                            <Link href={urls.admin.reports.providers}>
                                Provider performance
                                <ArrowUpRight />
                            </Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AdminRevenueReport.layout = {
    breadcrumbs: [
        {
            title: 'Revenue',
            href: urls.admin.reports.revenue,
        },
    ],
};
