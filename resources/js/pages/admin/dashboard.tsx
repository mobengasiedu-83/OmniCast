import { Head, Link } from '@inertiajs/react';
import {
    Activity,
    ArrowUpRight,
    BarChart3,
    Building2,
    ChartPie,
    History,
    Megaphone,
    ShieldCheck,
} from 'lucide-react';
import EmptyState from '@/components/empty-state';
import PageHeader from '@/components/page-header';
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

type Breakdown = {
    label: string;
    total: number;
};

type PageProps = {
    stats: {
        clients: number;
        pending_clients: number;
        campaigns: number;
        pending_campaigns: number;
        active_campaigns: number;
    };
    campaignActivity: Array<{
        date: string;
        label: string;
        total: number;
    }>;
    campaignStatusBreakdown: Array<
        Breakdown & {
            status: string;
        }
    >;
    channelBreakdown: Array<
        Breakdown & {
            channel: string;
        }
    >;
    recipientStatusBreakdown: Array<
        Breakdown & {
            status: string;
        }
    >;
    recentCampaigns: Array<{
        id: number;
        reference: string;
        name: string;
        client_name: string;
        channel: string;
        channel_label: string;
        status: string;
        status_label: string;
        estimated_recipients: number;
        created_at: string | null;
    }>;
};

const chartColors = [
    'var(--color-chart-1)',
    'var(--color-chart-2)',
    'var(--color-chart-3)',
    'var(--color-chart-4)',
    'var(--color-chart-5)',
];

function shortDate(value: string | null): string {
    return value
        ? new Date(value).toLocaleDateString(undefined, {
              day: '2-digit',
              month: 'short',
          })
        : '—';
}

function DonutBreakdown({
    data,
    description,
}: {
    data: Breakdown[];
    description: string;
}) {
    const slices = data.filter((item) => item.total > 0);
    const total = slices.reduce((sum, item) => sum + item.total, 0);

    if (total === 0) {
        return (
            <EmptyState
                icon={ChartPie}
                title="No data to chart yet"
                description={description}
            />
        );
    }

    const circumference = 2 * Math.PI * 38;
    let offset = 0;
    const segments = slices.map((slice, index) => {
        const length = (slice.total / total) * circumference;
        const segment = {
            ...slice,
            color: chartColors[index % chartColors.length],
            length,
            offset,
        };
        offset += length;

        return segment;
    });

    return (
        <div className="flex flex-col items-center gap-5 sm:flex-row">
            <svg
                viewBox="0 0 100 100"
                className="size-36 shrink-0"
                role="img"
                aria-label={`${total.toLocaleString()} total`}
            >
                <circle
                    cx="50"
                    cy="50"
                    r="38"
                    fill="none"
                    stroke="var(--color-muted)"
                    strokeWidth="12"
                />
                {segments.map((segment) => (
                    <circle
                        key={segment.label}
                        cx="50"
                        cy="50"
                        r="38"
                        fill="none"
                        stroke={segment.color}
                        strokeWidth="12"
                        strokeDasharray={`${segment.length} ${circumference - segment.length}`}
                        strokeDashoffset={-segment.offset}
                        transform="rotate(-90 50 50)"
                    />
                ))}
                <text
                    x="50"
                    y="47"
                    textAnchor="middle"
                    className="fill-foreground text-[13px] font-semibold"
                >
                    {total.toLocaleString()}
                </text>
                <text
                    x="50"
                    y="61"
                    textAnchor="middle"
                    className="fill-muted-foreground text-[7px]"
                >
                    total
                </text>
            </svg>

            <ul className="flex w-full flex-col gap-2.5">
                {segments.map((segment) => (
                    <li
                        key={segment.label}
                        className="flex items-center justify-between gap-3 text-sm"
                    >
                        <span className="flex min-w-0 items-center gap-2">
                            <span
                                className="size-2.5 shrink-0 rounded-full"
                                style={{ backgroundColor: segment.color }}
                            />
                            <span className="truncate">{segment.label}</span>
                        </span>
                        <span className="shrink-0 text-xs text-muted-foreground tabular-nums">
                            {segment.total.toLocaleString()} ·{' '}
                            {Math.round((segment.total / total) * 100)}%
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}

export default function AdminDashboard({
    stats,
    campaignActivity,
    campaignStatusBreakdown,
    channelBreakdown,
    recipientStatusBreakdown,
    recentCampaigns,
}: PageProps) {
    const overview = [
        {
            title: 'Client accounts',
            value: stats.clients,
            description: 'Across the platform',
            icon: Building2,
            tone: 'brand' as const,
        },
        {
            title: 'Pending clients',
            value: stats.pending_clients,
            description: 'Awaiting activation',
            icon: ShieldCheck,
            tone: 'warning' as const,
        },
        {
            title: 'Campaigns',
            value: stats.campaigns,
            description: `${stats.active_campaigns} active across all workspaces`,
            icon: Megaphone,
            tone: 'signal' as const,
        },
        {
            title: 'Pending approvals',
            value: stats.pending_campaigns,
            description: 'Campaigns awaiting review',
            icon: Activity,
            tone: 'warning' as const,
        },
    ];

    const activityPeak = Math.max(
        1,
        ...campaignActivity.map((day) => day.total),
    );
    const hasActivity = campaignActivity.some((day) => day.total > 0);

    return (
        <>
            <Head title="Platform dashboard" />

            <div className="flex flex-col gap-5">
                <PageHeader
                    icon={Activity}
                    title="Platform overview"
                    description="Client onboarding and campaign activity across OmniCast."
                    actions={
                        <Button variant="outline" asChild>
                            <Link href={urls.admin.reports.index}>
                                <BarChart3 />
                                View reports
                                <ArrowUpRight />
                            </Link>
                        </Button>
                    }
                />

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    {overview.map((item) => (
                        <StatCard key={item.title} {...item} />
                    ))}
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <BarChart3 className="size-4 text-brand" />
                                Campaign activity
                            </CardTitle>
                            <CardDescription>
                                Campaigns created over the last seven days.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {!hasActivity ? (
                                <EmptyState
                                    icon={Megaphone}
                                    title="No campaigns this week"
                                    description="New campaign activity will appear here."
                                />
                            ) : (
                                <div
                                    className="flex h-48 items-end gap-2 sm:gap-4"
                                    role="img"
                                    aria-label={campaignActivity
                                        .map(
                                            (day) =>
                                                `${day.date}: ${day.total} campaigns`,
                                        )
                                        .join(', ')}
                                >
                                    {campaignActivity.map((day) => (
                                        <div
                                            key={day.date}
                                            className="flex min-w-0 flex-1 flex-col items-center justify-end gap-2"
                                        >
                                            <span className="text-xs text-muted-foreground tabular-nums">
                                                {day.total}
                                            </span>
                                            <div className="flex h-32 w-full max-w-12 items-end overflow-hidden rounded-t-md bg-muted/60">
                                                <div
                                                    className="w-full rounded-t-md brand-gradient transition-[height]"
                                                    style={{
                                                        height: `${day.total === 0 ? 0 : Math.max((day.total / activityPeak) * 100, 8)}%`,
                                                    }}
                                                />
                                            </div>
                                            <span className="text-xs text-muted-foreground">
                                                {day.label}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Campaign status</CardTitle>
                            <CardDescription>
                                How campaigns are progressing.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <DonutBreakdown
                                data={campaignStatusBreakdown}
                                description="Campaign statuses will appear once campaigns are created."
                            />
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Campaign channels</CardTitle>
                            <CardDescription>
                                Campaign distribution by delivery channel.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <DonutBreakdown
                                data={channelBreakdown}
                                description="Channel usage will appear once campaigns are created."
                            />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Recipient outcomes</CardTitle>
                            <CardDescription>
                                Delivery progress across all campaign
                                recipients.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <DonutBreakdown
                                data={recipientStatusBreakdown}
                                description="Recipient outcomes will appear when campaigns begin sending."
                            />
                        </CardContent>
                    </Card>
                </div>

                <Card className="gap-0 overflow-hidden py-0">
                    <CardHeader className="flex flex-row items-start justify-between gap-3 border-b border-border/60 py-4">
                        <div className="space-y-1">
                            <CardTitle className="flex items-center gap-2">
                                <History className="size-4 text-brand" />
                                Recent campaigns
                            </CardTitle>
                            <CardDescription>
                                The six most recently created campaigns across
                                all clients.
                            </CardDescription>
                        </div>
                        <Button variant="ghost" size="sm" asChild>
                            <Link href={urls.campaigns.index}>
                                All campaigns
                                <ArrowUpRight />
                            </Link>
                        </Button>
                    </CardHeader>
                    <CardContent className="overflow-x-auto px-0">
                        {recentCampaigns.length === 0 ? (
                            <EmptyState
                                icon={Megaphone}
                                title="No campaigns yet"
                                description="Campaigns will appear here after a client creates one."
                            />
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="pl-6">
                                            Campaign
                                        </TableHead>
                                        <TableHead>Client</TableHead>
                                        <TableHead>Channel</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">
                                            Recipients
                                        </TableHead>
                                        <TableHead className="pr-6 text-right">
                                            Created
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {recentCampaigns.map((campaign) => (
                                        <TableRow key={campaign.id}>
                                            <TableCell className="pl-6">
                                                <Link
                                                    href={urls.campaigns.show(
                                                        campaign.id,
                                                    )}
                                                    className="font-medium transition-colors hover:text-brand"
                                                >
                                                    {campaign.name}
                                                </Link>
                                                <p className="text-xs text-muted-foreground">
                                                    {campaign.reference}
                                                </p>
                                            </TableCell>
                                            <TableCell>
                                                {campaign.client_name}
                                            </TableCell>
                                            <TableCell>
                                                {campaign.channel_label}
                                            </TableCell>
                                            <TableCell>
                                                <StatusBadge
                                                    status={campaign.status}
                                                    label={
                                                        campaign.status_label
                                                    }
                                                />
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {campaign.estimated_recipients.toLocaleString()}
                                            </TableCell>
                                            <TableCell className="pr-6 text-right text-xs text-muted-foreground">
                                                {shortDate(campaign.created_at)}
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
