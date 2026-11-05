import { Head, Link } from '@inertiajs/react';
import {
    CheckCheck,
    Download,
    Mail,
    MessageSquareText,
    PhoneCall,
    Send,
    Timer,
    TriangleAlert,
    Users,
    Wallet,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
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
import type { ChannelDeliveryReport as ChannelReport, ClientOption } from '@/types';

type PageProps = {
    filters: {
        from: string;
        to: string;
        channel?: string;
        client_id: number | null;
    };
    period: { label: string; days: number };
    channel: ChannelReport['channel'];
    totals: ChannelReport['totals'];
    daily: ChannelReport['daily'];
    byClient: ChannelReport['byClient'];
    clients: ClientOption[];
    can: { export: boolean };
};

/** The icon and export link each channel screen uses. */
const channels: Record<
    string,
    { icon: LucideIcon; href: string; export: string }
> = {
    voice: {
        icon: PhoneCall,
        href: urls.admin.reports.voice,
        export: 'voice',
    },
    sms: {
        icon: MessageSquareText,
        href: urls.admin.reports.sms,
        export: 'sms',
    },
    email: {
        icon: Mail,
        href: urls.admin.reports.email,
        export: 'email',
    },
};

function money(value: number): string {
    return `GHS ${value.toLocaleString('en-GH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

/**
 * One channel across every client: volume, delivery, spend, the daily trend and
 * the accounts that pushed the most traffic.
 */
export default function AdminChannelReport({
    filters,
    period,
    channel,
    totals,
    daily,
    byClient,
    clients,
    can,
}: PageProps) {
    const screen = channels[channel.value] ?? channels.sms;
    const Icon = screen.icon;
    const peak = Math.max(...daily.map((day) => day.total), 1);

    return (
        <>
            <Head title={`${channel.label} report`} />

            <div className="flex flex-col gap-4">
                <PageHeader
                    icon={Icon}
                    title={`${channel.label} report`}
                    description={`Every ${channel.unit.toLowerCase().replace(/s$/, '')} the platform attempted, counted from what the provider confirmed rather than what the dispatcher handed over.`}
                    badge={
                        <span className="text-muted-foreground text-xs">
                            {period.label}
                        </span>
                    }
                    actions={
                        can.export ? (
                            <Button asChild variant="outline">
                                <Link
                                    href={`${urls.admin.reports.exports.index}?report=${screen.export}&from=${filters.from}&to=${filters.to}`}
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

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        title={channel.unit}
                        value={totals.total.toLocaleString()}
                        description={`${totals.clients} client(s) sent traffic`}
                        icon={Send}
                        tone="brand"
                    />
                    <StatCard
                        title="Delivered"
                        value={totals.delivered.toLocaleString()}
                        description={
                            totals.delivery_rate === null
                                ? 'Nothing attempted yet'
                                : `${totals.delivery_rate}% of attempts`
                        }
                        icon={CheckCheck}
                        tone="success"
                    />
                    <StatCard
                        title="Failed"
                        value={totals.failed.toLocaleString()}
                        description="Rejected, bounced or expired"
                        icon={TriangleAlert}
                        tone={totals.failed > 0 ? 'danger' : 'neutral'}
                    />
                    <StatCard
                        title="In flight"
                        value={totals.pending.toLocaleString()}
                        description="Handed over, no outcome yet"
                        icon={Timer}
                        tone="warning"
                    />
                    <StatCard
                        title="Spend"
                        value={money(totals.spend)}
                        description="Charged to the client wallets"
                        icon={Wallet}
                        tone="signal"
                    />
                    <StatCard
                        title="Delivery rate"
                        value={
                            totals.delivery_rate === null
                                ? '—'
                                : `${totals.delivery_rate}%`
                        }
                        description={`Against ${totals.total.toLocaleString()} attempt(s)`}
                        icon={CheckCheck}
                        tone="signal"
                    />
                    <StatCard
                        title="Clients"
                        value={totals.clients.toLocaleString()}
                        description="Accounts with traffic in this window"
                        icon={Users}
                        tone="neutral"
                    />
                    <StatCard
                        title="Window"
                        value={`${period.days}d`}
                        description={period.label}
                        icon={Timer}
                        tone="neutral"
                    />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Daily volume</CardTitle>
                        <CardDescription>
                            What the platform attempted each day inside the
                            selected window.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {daily.length === 0 ? (
                            <EmptyState
                                icon={Icon}
                                title={`No ${channel.unit.toLowerCase()} in this window`}
                                description="Nothing was attempted between these dates."
                            />
                        ) : (
                            <div className="flex flex-col gap-2.5">
                                {daily.map((day) => (
                                    <div
                                        key={day.date}
                                        className="flex items-center gap-3"
                                    >
                                        <span className="text-muted-foreground w-16 text-xs">
                                            {day.label}
                                        </span>
                                        <div className="bg-muted h-2 flex-1 overflow-hidden rounded-full">
                                            <div
                                                className="brand-gradient h-full rounded-full"
                                                style={{
                                                    width: `${(day.delivered / peak) * 100}%`,
                                                }}
                                            />
                                        </div>
                                        <span className="w-40 text-right text-xs tabular-nums">
                                            {day.delivered.toLocaleString()} of{' '}
                                            {day.total.toLocaleString()} ·{' '}
                                            {day.failed.toLocaleString()} failed
                                        </span>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                        <ReportFilters
                            url={screen.href}
                            values={filters}
                            clients={clients}
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>By client</CardTitle>
                        <CardDescription>
                            The {byClient.length} account(s) with the most
                            traffic on this channel.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="px-0">
                        {byClient.length === 0 ? (
                            <EmptyState
                                icon={Users}
                                title="No client traffic"
                                description="Clients appear here once they send through this channel inside the selected dates."
                            />
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="pl-6">
                                            Client
                                        </TableHead>
                                        <TableHead className="text-right">
                                            {channel.unit}
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Delivered
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Failed
                                        </TableHead>
                                        <TableHead className="text-right">
                                            In flight
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Rate
                                        </TableHead>
                                        <TableHead className="pr-6 text-right">
                                            Spend
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {byClient.map((row) => (
                                        <TableRow key={row.client_id}>
                                            <TableCell className="pl-6 font-medium">
                                                {row.name}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {row.total.toLocaleString()}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {row.delivered.toLocaleString()}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {row.failed.toLocaleString()}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground text-right tabular-nums">
                                                {row.pending.toLocaleString()}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {row.delivery_rate === null
                                                    ? '—'
                                                    : `${row.delivery_rate}%`}
                                            </TableCell>
                                            <TableCell className="pr-6 text-right tabular-nums">
                                                {money(row.spend)}
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

AdminChannelReport.layout = {
    breadcrumbs: [
        {
            title: 'Reports',
            href: urls.admin.reports.index,
        },
    ],
};

