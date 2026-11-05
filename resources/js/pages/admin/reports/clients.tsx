import { Head } from '@inertiajs/react';
import {
    Building2,
    CheckCheck,
    Coins,
    Download,
    ReceiptText,
    Send,
    Users,
    Wallet,
} from 'lucide-react';
import EmptyState from '@/components/empty-state';
import PageHeader from '@/components/page-header';
import ReportFilters from '@/components/report-filters';
import StatCard from '@/components/stat-card';
import StatusBadge from '@/components/status-badge';
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
    ClientPerformanceRow,
    ClientPerformanceTotals,
} from '@/types';

type PageProps = {
    filters: {
        from: string;
        to: string;
        client_id: number | null;
    };
    period: { label: string; days: number };
    report: { rows: ClientPerformanceRow[]; totals: ClientPerformanceTotals };
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
 * One row per client: what it sent, what landed, what it cost and what it owes,
 * so an account manager can compare tenants without opening each one.
 */
export default function AdminClientReports({
    filters,
    period,
    report,
    clients,
    can,
}: PageProps) {
    const totals = report.totals;

    return (
        <>
            <Head title="Client performance" />

            <div className="flex flex-col gap-4">
                <PageHeader
                    icon={Building2}
                    title="Client performance"
                    description="Every tenant side by side: delivery, spend and the wallet position behind it."
                    badge={
                        <span className="text-xs text-muted-foreground">
                            {period.label}
                        </span>
                    }
                    actions={
                        can.export ? (
                            <Button asChild variant="outline">
                                <a
                                    href={`${urls.admin.reports.exports.index}?report=clients&from=${filters.from}&to=${filters.to}`}
                                >
                                    <Download />
                                    Export
                                </a>
                            </Button>
                        ) : undefined
                    }
                />

                <Card className="py-4">
                    <CardContent>
                        <ReportFilters
                            url={urls.admin.reports.clients}
                            values={filters}
                            clients={clients}
                        />
                    </CardContent>
                </Card>

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        title="Clients"
                        value={totals.clients}
                        description={`${totals.campaigns} campaign(s) in the window`}
                        icon={Building2}
                        tone="brand"
                    />
                    <StatCard
                        title="Recipients"
                        value={totals.recipients.toLocaleString()}
                        description="Estimated across every campaign"
                        icon={Users}
                        tone="brand"
                    />
                    <StatCard
                        title="Delivered"
                        value={totals.delivered.toLocaleString()}
                        description={`${totals.failed.toLocaleString()} failed`}
                        icon={CheckCheck}
                        tone="success"
                    />
                    <StatCard
                        title="Spend"
                        value={money(totals.spend)}
                        description="Charged to the wallets"
                        icon={Coins}
                        tone="signal"
                    />
                    <StatCard
                        title="Revenue"
                        value={money(totals.revenue)}
                        description="Settled payments in the window"
                        icon={ReceiptText}
                        tone="signal"
                    />
                    <StatCard
                        title="Wallet balance"
                        value={money(totals.balance)}
                        description="Across every client"
                        icon={Wallet}
                        tone="neutral"
                    />
                    <StatCard
                        title="Reserved"
                        value={money(totals.reserved)}
                        description="Held for campaigns in flight"
                        icon={Wallet}
                        tone="warning"
                    />
                    <StatCard
                        title="Window"
                        value={`${period.days}d`}
                        description={period.label}
                        icon={Send}
                        tone="neutral"
                    />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Per client</CardTitle>
                        <CardDescription>
                            Delivery rate is measured on attempts, so a client
                            that skipped most of its audience still shows the
                            real outcome.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="px-0">
                        {report.rows.length === 0 ? (
                            <EmptyState
                                icon={Building2}
                                title="No client activity"
                                description="No campaign was created inside this window."
                            />
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="pl-6">
                                            Client
                                        </TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">
                                            Campaigns
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Recipients
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Delivered
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Failed
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Rate
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Spend
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Revenue
                                        </TableHead>
                                        <TableHead className="pr-6 text-right">
                                            Balance
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {report.rows.map((row) => (
                                        <TableRow key={row.id}>
                                            <TableCell className="pl-6 font-medium">
                                                {row.name}
                                            </TableCell>
                                            <TableCell>
                                                <StatusBadge
                                                    status={row.status}
                                                    label={row.status_label}
                                                />
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {row.campaigns}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {row.recipients.toLocaleString()}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {row.delivered.toLocaleString()}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {row.failed.toLocaleString()}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {row.delivery_rate === null
                                                    ? '—'
                                                    : `${row.delivery_rate}%`}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {money(row.spend)}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {money(row.revenue)}
                                            </TableCell>
                                            <TableCell className="pr-6 text-right tabular-nums">
                                                {money(row.balance)}
                                                {row.reserved > 0 && (
                                                    <span className="block text-xs text-muted-foreground">
                                                        {money(row.reserved)}{' '}
                                                        reserved
                                                    </span>
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
        </>
    );
}

AdminClientReports.layout = {
    breadcrumbs: [
        {
            title: 'Client performance',
            href: urls.admin.reports.clients,
        },
    ],
};
