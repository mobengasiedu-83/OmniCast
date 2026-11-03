import { Head, Link } from '@inertiajs/react';
import {
    Activity,
    ArrowUpRight,
    Coins,
    Layers,
    Megaphone,
    MessageSquare,
    Phone,
    Plus,
    Send,
    Users,
    Wallet as WalletIcon,
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
import { cn } from '@/lib/utils';
import { urls } from '@/lib/urls';
import type {
    ChannelBreakdown,
    DashboardCampaign,
    DashboardStats,
    WalletSummary,
} from '@/types';

type PageProps = {
    stats: DashboardStats;
    wallet: WalletSummary | null;
    channelBreakdown: ChannelBreakdown[];
    recentCampaigns: DashboardCampaign[];
};

function money(value: number): string {
    return `GHS ${value.toLocaleString('en-GH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

/** Compact date for table columns where the time of day adds no value. */
function shortDate(value: string | null): string {
    return value
        ? new Date(value).toLocaleDateString(undefined, {
              day: '2-digit',
              month: 'short',
              year: 'numeric',
          })
        : '—';
}

/**
 * Voice rides the brand gradient and SMS rides the aqua signal colour, so a
 * channel reads the same everywhere it appears.
 */
function channelStyle(channel: string) {
    if (channel === 'voice') {
        return { bar: 'brand-gradient', icon: Phone };
    }

    if (channel === 'sms') {
        return { bar: 'bg-signal', icon: MessageSquare };
    }

    return { bar: 'bg-chart-4', icon: Layers };
}

export default function Dashboard({
    stats,
    wallet,
    channelBreakdown,
    recentCampaigns,
}: PageProps) {
    const overview = [
        {
            title: 'Campaigns',
            value: stats.campaigns,
            description: `${stats.active_campaigns} in flight`,
            icon: Megaphone,
            tone: 'brand' as const,
        },
        {
            title: 'Awaiting approval',
            value: stats.awaiting_approval,
            description: 'Submitted and waiting for a reviewer',
            icon: Send,
            tone: 'warning' as const,
        },
        {
            title: 'Contacts',
            value: stats.contacts,
            description: 'Across every contact list',
            icon: Users,
            tone: 'signal' as const,
        },
        {
            title: 'Spend this month',
            value: money(stats.spend_this_month),
            description: `${stats.delivered} messages delivered`,
            icon: Coins,
            tone: 'success' as const,
        },
    ];

    const channelTotal = channelBreakdown.reduce(
        (total, row) => total + row.total,
        0,
    );

    return (
        <>
            <Head title="Dashboard" />

            <div className="flex flex-col gap-5">
                <PageHeader
                    icon={Activity}
                    title="Delivery overview"
                    description="Voice and SMS performance for this workspace."
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <Link href={urls.campaigns.index}>
                                    All campaigns
                                </Link>
                            </Button>
                            <Button asChild>
                                <Link href={urls.campaigns.create}>
                                    <Plus />
                                    New campaign
                                </Link>
                            </Button>
                        </>
                    }
                />

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    {overview.map((item, index) => (
                        <StatCard
                            key={item.title}
                            {...item}
                            className="animate-fade-in-up"
                            style={{ animationDelay: `${index * 60}ms` }}
                        />
                    ))}
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <Card className="gap-0 overflow-hidden py-0 lg:col-span-2">
                        <CardHeader className="flex flex-row items-center justify-between gap-2 border-b border-border/60 py-4">
                            <div className="space-y-1">
                                <CardTitle>Recent campaigns</CardTitle>
                                <CardDescription>
                                    The six most recently created campaigns.
                                </CardDescription>
                            </div>

                            <Button variant="ghost" size="sm" asChild>
                                <Link href={urls.campaigns.index}>
                                    View all
                                    <ArrowUpRight />
                                </Link>
                            </Button>
                        </CardHeader>

                        <CardContent className="px-0">
                            {recentCampaigns.length === 0 ? (
                                <EmptyState
                                    icon={Megaphone}
                                    title="No campaigns yet"
                                    description="Draft a campaign, submit it for approval, then launch it to see delivery data land here."
                                    action={
                                        <Button size="sm" asChild>
                                            <Link href={urls.campaigns.create}>
                                                <Plus />
                                                New campaign
                                            </Link>
                                        </Button>
                                    }
                                />
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Campaign</TableHead>
                                            <TableHead>Channel</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead className="text-right">
                                                Recipients
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Cost
                                            </TableHead>
                                            <TableHead className="pr-4">
                                                Created
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>

                                    <TableBody>{recentCampaigns.map((campaign) => {
                                            const { bar, icon: Icon } =
                                                channelStyle(campaign.channel);

                                            return (
                                                <TableRow key={campaign.id}>
                                                    <TableCell>
                                                        <Link
                                                            href={urls.campaigns.show(
                                                                campaign.id,
                                                            )}
                                                            className="hover:text-brand font-medium transition-colors"
                                                        >
                                                            {campaign.name}
                                                        </Link>
                                                        <p className="text-muted-foreground text-xs">
                                                            {
                                                                campaign.reference
                                                            }
                                                        </p>
                                                    </TableCell>

                                                    <TableCell>
                                                        <span className="flex items-center gap-2 text-sm">
                                                            <span
                                                                className={cn(
                                                                    'flex size-6 items-center justify-center rounded-md text-white',
                                                                    bar,
                                                                )}
                                                            >
                                                                <Icon className="size-3" />
                                                            </span>
                                                            {
                                                                campaign.channel_label
                                                            }
                                                        </span>
                                                    </TableCell>

                                                    <TableCell>
                                                        <StatusBadge
                                                            status={
                                                                campaign.status
                                                            }
                                                            label={
                                                                campaign.status_label
                                                            }
                                                        />
                                                    </TableCell>

                                                    <TableCell className="text-right tabular-nums">
                                                        {campaign.estimated_recipients ??
                                                            '—'}
                                                    </TableCell>

                                                    <TableCell className="text-right tabular-nums">
                                                        {money(
                                                            campaign.actual_cost >
                                                                0
                                                                ? campaign.actual_cost
                                                                : campaign.estimated_cost,
                                                        )}
                                                    </TableCell>

                                                    <TableCell className="text-muted-foreground pr-4 text-xs">
                                                        {shortDate(
                                                            campaign.created_at,
                                                        )}
                                                    </TableCell>
                                                </TableRow>
                                            );
                                        })}</TableBody>
                                </Table>
                            )}
                        </CardContent>
                    </Card>

                    <div className="flex flex-col gap-4">
                                                {wallet === null ? (
                            <Card className="py-5">
                                <CardHeader>
                                    <CardTitle>Wallet</CardTitle>
                                    <CardDescription>
                                        Select a client account to view its
                                        wallet.
                                    </CardDescription>
                                </CardHeader>
                            </Card>
                        ) : (
                            <Card className="relative gap-0 overflow-hidden border-0 py-0">
                                <div className="brand-gradient absolute inset-0" />
                                <div className="absolute inset-0 bg-[#0b0a1f]/55" />
                                <div className="dot-pattern absolute inset-0 opacity-20" />

                                <div className="relative space-y-4 p-5 text-white">
                                    <div className="flex items-center justify-between">
                                        <p className="text-[11px] font-semibold tracking-[0.16em] text-white/75 uppercase">
                                            Wallet
                                        </p>
                                        <WalletIcon className="size-4 text-white/70" />
                                    </div>

                                    <div className="space-y-1">
                                        <p className="text-3xl font-semibold tracking-tight tabular-nums">
                                            {money(wallet.available)}
                                        </p>
                                        <p className="text-xs text-white/70">
                                            Available credit
                                        </p>
                                    </div>

                                    <div className="grid grid-cols-2 gap-2 text-xs">
                                        <div className="rounded-lg bg-white/10 p-2.5 ring-1 ring-white/15">
                                            <p className="text-white/60">
                                                Balance
                                            </p>
                                            <p className="font-medium tabular-nums">
                                                {money(wallet.balance)}
                                            </p>
                                        </div>
                                        <div className="rounded-lg bg-white/10 p-2.5 ring-1 ring-white/15">
                                            <p className="text-white/60">
                                                Reserved
                                            </p>
                                            <p className="font-medium tabular-nums">
                                                {money(wallet.reserved)}
                                            </p>
                                        </div>
                                    </div>

                                    {wallet.is_low && (
                                        <p className="rounded-lg bg-white/15 px-3 py-2 text-xs ring-1 ring-white/25">
                                            Below the{' '}
                                            {money(
                                                wallet.low_balance_threshold,
                                            )}{' '}
                                            threshold — top up before launching.
                                        </p>
                                    )}

                                    <Button
                                        variant="secondary"
                                        size="sm"
                                        className="w-full"
                                        asChild
                                    >
                                        <Link href={urls.wallet.index}>
                                            Open wallet
                                            <ArrowUpRight />
                                        </Link>
                                    </Button>
                                </div>
                            </Card>
                        )}

                                                <Card className="py-5">
                            <CardHeader>
                                <CardTitle>Channel mix</CardTitle>
                                <CardDescription>
                                    Campaigns per channel
                                </CardDescription>
                            </CardHeader>

                            <CardContent className="space-y-3.5">
                                {channelBreakdown.length === 0 ? (
                                    <p className="text-muted-foreground text-sm">
                                        No campaigns yet.
                                    </p>
                                ) : (
                                    channelBreakdown.map((row) => {
                                        const share =
                                            channelTotal === 0
                                                ? 0
                                                : Math.round(
                                                      (row.total /
                                                          channelTotal) *
                                                          100,
                                                  );
                                        const { bar, icon: Icon } =
                                            channelStyle(row.channel);

                                        return (
                                            <div
                                                key={row.channel}
                                                className="space-y-1.5"
                                            >
                                                <div className="flex items-center justify-between text-sm">
                                                    <span className="flex items-center gap-2">
                                                        <Icon className="text-muted-foreground size-3.5" />
                                                        {row.label}
                                                    </span>
                                                    <span className="text-muted-foreground text-xs tabular-nums">
                                                        {row.total} · {share}%
                                                    </span>
                                                </div>

                                                <div className="bg-muted h-2 overflow-hidden rounded-full">
                                                    <div
                                                        className={cn(
                                                            'animate-grow-x h-full origin-left rounded-full',
                                                            bar,
                                                        )}
                                                        style={{
                                                            width: `${share}%`,
                                                        }}
                                                    />
                                                </div>
                                            </div>
                                        );
                                    })
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: urls.dashboard,
        },
    ],
};
