import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowUpRight,
    BarChart3,
    CheckCheck,
    Coins,
    Download,
    MessageSquare,
    Phone,
    Send,
    Users,
} from 'lucide-react';
import { useState, type FormEvent } from 'react';
import EmptyState from '@/components/empty-state';
import PageHeader from '@/components/page-header';
import StatCard from '@/components/stat-card';
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
    MonthlyReport,
    ReportOption,
    ReportPeriod,
    ReportSummary,
    TopCampaign,
} from '@/types';

type PageProps = {
    filters: { from: string; to: string; channel: string };
    period: ReportPeriod;
    summary: ReportSummary & { pending: number };
    byChannel: ChannelReport[];
    monthly: MonthlyReport[];
    topCampaigns: TopCampaign[];
    channels: ReportOption[];
    can: { export: boolean };
};

function money(value: number): string {
    return `GHS ${value.toLocaleString('en-GH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

const channelIcons: Record<string, typeof Phone> = {
    voice: Phone,
    sms: MessageSquare,
    email: Send,
    multi_channel: Users,
};

/**
 * The tenant's delivery and spend overview: what moved, what it cost and where
 * the volume came from, with the six-month trend underneath.
 */
export default function ReportsOverview({
    filters,
    period,
    summary,
    byChannel,
    monthly,
    topCampaigns,
    channels,
    can,
}: PageProps) {
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);

    const apply = (overrides: Record<string, string | null>): void => {
        router.get(
            urls.reports.index,
            {
                from: filters.from,
                to: filters.to,
                channel: filters.channel,
                ...overrides,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        apply({ from: from || null, to: to || null });
    };

    const peak = Math.max(...monthly.map((month) => month.spend), 1);

    return (
        <>
            <Head title="Reports" />

            <div className="flex flex-col gap-4">
                <PageHeader
                    icon={BarChart3}
                    title="Reports"
                    description="Delivery, audience and spend across every campaign in the selected window."
                    badge={
                        <span className="text-xs text-muted-foreground">
                            {period.label}
                        </span>
                    }
                    actions={
                        can.export ? (
                            <Button asChild variant="outline">
                                <Link
                                    href={`${urls.reports.exports.index}?report=overview&from=${filters.from}&to=${filters.to}`}
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
                        <form
                            onSubmit={submit}
                            className="flex flex-wrap items-end gap-3"
                        >
                            <div className="grid gap-1.5">
                                <Label htmlFor="from">From</Label>
                                <Input
                                    id="from"
                                    type="date"
                                    value={from}
                                    onChange={(event) =>
                                        setFrom(event.target.value)
                                    }
                                />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="to">To</Label>
                                <Input
                                    id="to"
                                    type="date"
                                    value={to}
                                    onChange={(event) =>
                                        setTo(event.target.value)
                                    }
                                />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="channel">Channel</Label>
                                <Select
                                    value={filters.channel || 'all'}
                                    onValueChange={(value) =>
                                        apply({
                                            channel:
                                                value === 'all' ? null : value,
                                        })
                                    }
                                >
                                    <SelectTrigger
                                        id="channel"
                                        className="w-44"
                                    >
                                        <SelectValue placeholder="Every channel" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            Every channel
                                        </SelectItem>
                                        {channels.map((channel) => (
                                            <SelectItem
                                                key={channel.value}
                                                value={channel.value}
                                            >
                                                {channel.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <Button type="submit" variant="secondary">
                                Apply
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        title="Recipients"
                        value={summary.recipients}
                        description={`${summary.campaigns} campaign(s) created`}
                        icon={Users}
                        tone="brand"
                    />
                    <StatCard
                        title="Delivered"
                        value={summary.delivered}
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
                        value={summary.failed}
                        description={`${summary.skipped} skipped before sending`}
                        icon={Send}
                        tone={summary.failed > 0 ? 'danger' : 'neutral'}
                    />
                    <StatCard
                        title="Spend"
                        value={money(summary.spend)}
                        description={`${money(summary.reserved)} still reserved`}
                        icon={Coins}
                        tone="signal"
                    />
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>By channel</CardTitle>
                            <CardDescription>
                                Where the volume and the spend sit inside this
                                window.
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
                                            Audience
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
                                            BarChart3;

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
                                The last six months of campaign spend.
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
                                                width: `${(month.spend / peak) * 100}%`,
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

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between gap-2">
                        <div className="space-y-1">
                            <CardTitle>Top campaigns</CardTitle>
                            <CardDescription>
                                The five campaigns that spent the most.
                            </CardDescription>
                        </div>
                        <Button variant="ghost" size="sm" asChild>
                            <Link href={urls.reports.campaigns}>
                                All campaigns
                                <ArrowUpRight />
                            </Link>
                        </Button>
                    </CardHeader>
                    <CardContent className="px-0">
                        {topCampaigns.length === 0 ? (
                            <EmptyState
                                icon={BarChart3}
                                title="Nothing in this window"
                                description="Campaigns appear here once they are created inside the selected dates."
                            />
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="pl-4">
                                            Campaign
                                        </TableHead>
                                        <TableHead>Channel</TableHead>
                                        <TableHead className="text-right">
                                            Audience
                                        </TableHead>
                                        <TableHead className="pr-4 text-right">
                                            Spend
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {topCampaigns.map((campaign) => (
                                        <TableRow key={campaign.id}>
                                            <TableCell className="pl-4">
                                                <Link
                                                    href={urls.campaigns.show(
                                                        campaign.id,
                                                    )}
                                                    className="font-medium hover:underline"
                                                >
                                                    {campaign.name}
                                                </Link>
                                                <p className="text-xs text-muted-foreground">
                                                    {campaign.reference}
                                                </p>
                                            </TableCell>
                                            <TableCell>
                                                {campaign.channel_label}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {(
                                                    campaign.recipients ?? 0
                                                ).toLocaleString()}
                                            </TableCell>
                                            <TableCell className="pr-4 text-right tabular-nums">
                                                {money(campaign.spend)}
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

ReportsOverview.layout = {
    breadcrumbs: [
        {
            title: 'Reports',
            href: urls.reports.index,
        },
    ],
};
