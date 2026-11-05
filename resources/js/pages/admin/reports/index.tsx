import { Head, Link } from '@inertiajs/react';
import {
    ArrowUpRight,
    BarChart3,
    Building2,
    CheckCheck,
    Coins,
    Download,
    Mail,
    MessageSquareText,
    PhoneCall,
    ReceiptText,
    Send,
    Sparkles,
    Users,
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
    ChannelReport,
    ClientOption,
    ExecutiveSummary,
    MonthlyReport,
    ReportTrendPoint,
    TopClientRow,
} from '@/types';

type PageProps = {
    filters: {
        from: string;
        to: string;
        channel?: string;
        client_id: number | null;
    };
    period: { label: string; days: number };
    summary: ExecutiveSummary;
    byChannel: ChannelReport[];
    monthly: MonthlyReport[];
    topClients: TopClientRow[];
    trend: ReportTrendPoint[];
    clients: ClientOption[];
    can: { export: boolean };
};

/** The icon each channel row shows. */
const channelIcons: Record<string, typeof PhoneCall> = {
    voice: PhoneCall,
    sms: MessageSquareText,
    email: Mail,
    multi_channel: Sparkles,
};

function money(value: number): string {
    return `GHS ${value.toLocaleString('en-GH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

/**
 * The platform's headline view: every client's delivery, audience and money in
 * one window, with the tenants that moved the most volume underneath.
 */
export default function AdminReportsOverview({
    filters,
    period,
    summary,
    byChannel,
    monthly,
    topClients,
    trend,
    clients,
    can,
}: PageProps) {
    const peakSpend = Math.max(...monthly.map((month) => month.spend), 1);
    const peakRecipients = Math.max(...trend.map((day) => day.recipients), 1);

    return (
        <>
            <Head title="Executive report" />

            <div className="flex flex-col gap-4">
                <PageHeader
                    icon={BarChart3}
                    title="Executive report"
                    description="Delivery, audience and money across every client account, read from the same ledgers the tenants are billed on."
                    badge={
                        <span className="text-xs text-muted-foreground">
                            {period.label}
                        </span>
                    }
                    actions={
                        can.export ? (
                            <Button asChild variant="outline">
                                <Link
                                    href={`${urls.admin.reports.exports.index}?report=executive&from=${filters.from}&to=${filters.to}`}
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
                            url={urls.admin.reports.index}
                            values={filters}
                            clients={clients}
                        />
                    </CardContent>
                </Card>

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        title="Clients"
                        value={summary.clients}
                        description={`${summary.active_clients} active · ${summary.new_clients} new`}
                        icon={Building2}
                        tone="brand"
                    />
                    <StatCard
                        title="Delivered"
                        value={summary.delivered.toLocaleString()}
                        description={
                            summary.delivery_rate === null
                                ? 'Nothing attempted yet'
                                : `${summary.delivery_rate}% of attempts`
                        }
                        icon={CheckCheck}
                        tone="success"
                    />
                    <StatCard
                        title="Failed"
                        value={summary.failed.toLocaleString()}
                        description={`${summary.skipped.toLocaleString()} skipped before sending`}
                        icon={Send}
                        tone={summary.failed > 0 ? 'danger' : 'neutral'}
                    />
                    <StatCard
                        title="Revenue"
                        value={money(summary.revenue)}
                        description={`${summary.payments} settled payment(s)`}
                        icon={ReceiptText}
                        tone="signal"
                    />
                    <StatCard
                        title="Recipients"
                        value={summary.recipients.toLocaleString()}
                        description={`${summary.campaigns} campaign(s) created`}
                        icon={Users}
                        tone="brand"
                    />
                    <StatCard
                        title="Campaign spend"
                        value={money(summary.spend)}
                        description={`${money(summary.reserved)} still reserved`}
                        icon={Coins}
                        tone="signal"
                    />
                    <StatCard
                        title="Wallet balance"
                        value={money(summary.wallet_balance)}
                        description={`${money(summary.wallet_reserved)} reserved`}
                        icon={Wallet}
                        tone="neutral"
                    />
                    <StatCard
                        title="In flight"
                        value={summary.pending.toLocaleString()}
                        description={`${period.label} · ${period.days} days`}
                        icon={BarChart3}
                        tone="warning"
                    />
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>By channel</CardTitle>
                            <CardDescription>
                                Where the volume and the spend sit across the
                                platform.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Channel</TableHead>
                                        <TableHead className="text-right">
                                            Campaigns
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Recipients
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Spend
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {byChannel.map((row) => {
                                        const Icon =
                                            channelIcons[row.channel] ??
                                            MessageSquareText;

                                        return (
                                            <TableRow key={row.channel}>
                                                <TableCell>
                                                    <span className="flex items-center gap-2">
                                                        <Icon className="size-4 text-muted-foreground" />
                                                        {row.label}
                                                    </span>
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {row.campaigns}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {row.recipients.toLocaleString()}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {money(row.spend)}
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Spend trend</CardTitle>
                            <CardDescription>
                                Campaign spend over the last six months.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-2.5">
                            {monthly.map((month) => (
                                <div
                                    key={month.month}
                                    className="flex items-center gap-3"
                                >
                                    <span className="w-16 text-xs text-muted-foreground">
                                        {month.label}
                                    </span>
                                    <div className="h-2 flex-1 overflow-hidden rounded-full bg-muted">
                                        <div
                                            className="h-full rounded-full brand-gradient"
                                            style={{
                                                width: `${(month.spend / peakSpend) * 100}%`,
                                            }}
                                        />
                                    </div>
                                    <span className="w-24 text-right text-xs tabular-nums">
                                        {money(month.spend)}
                                    </span>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Daily recipients</CardTitle>
                            <CardDescription>
                                What the platform attempted each day inside the
                                window.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {trend.length === 0 ? (
                                <EmptyState
                                    icon={BarChart3}
                                    title="No traffic in this window"
                                    description="Nothing was attempted between these dates."
                                />
                            ) : (
                                <div className="flex flex-col gap-2.5">
                                    {trend.map((day) => (
                                        <div
                                            key={day.date}
                                            className="flex items-center gap-3"
                                        >
                                            <span className="w-20 text-xs text-muted-foreground">
                                                {day.date}
                                            </span>
                                            <div className="h-2 flex-1 overflow-hidden rounded-full bg-muted">
                                                <div
                                                    className="h-full rounded-full brand-gradient"
                                                    style={{
                                                        width: `${(day.recipients / peakRecipients) * 100}%`,
                                                    }}
                                                />
                                            </div>
                                            <span className="w-32 text-right text-xs tabular-nums">
                                                {day.delivered.toLocaleString()}{' '}
                                                of{' '}
                                                {day.recipients.toLocaleString()}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-start justify-between gap-2">
                            <div className="space-y-1">
                                <CardTitle>Top clients</CardTitle>
                                <CardDescription>
                                    The five accounts that spent the most.
                                </CardDescription>
                            </div>
                            <Button variant="ghost" size="sm" asChild>
                                <Link href={urls.admin.reports.clients}>
                                    All clients
                                    <ArrowUpRight />
                                </Link>
                            </Button>
                        </CardHeader>
                        <CardContent className="px-0">
                            {topClients.length === 0 ? (
                                <EmptyState
                                    icon={Building2}
                                    title="Nothing in this window"
                                    description="Clients appear here once a campaign is created inside the selected dates."
                                />
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="pl-6">
                                                Client
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Campaigns
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Recipients
                                            </TableHead>
                                            <TableHead className="pr-6 text-right">
                                                Spend
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {topClients.map((client) => (
                                            <TableRow key={client.client_id}>
                                                <TableCell className="pl-6 font-medium">
                                                    {client.name}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {client.campaigns}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {client.recipients.toLocaleString()}
                                                </TableCell>
                                                <TableCell className="pr-6 text-right tabular-nums">
                                                    {money(client.spend)}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

AdminReportsOverview.layout = {
    breadcrumbs: [
        {
            title: 'Executive report',
            href: urls.admin.reports.index,
        },
    ],
};
