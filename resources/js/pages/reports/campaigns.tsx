import { Head, Link } from '@inertiajs/react';
import { Download, Megaphone } from 'lucide-react';
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
    CampaignPerformanceReport,
    ReportCampaignOption,
    ReportOption,
    ReportPeriod,
} from '@/types';

type PageProps = {
    filters: {
        from: string;
        to: string;
        campaign: number | null;
        channel: string;
    };
    period: ReportPeriod;
    report: CampaignPerformanceReport;
    channels: ReportOption[];
    campaigns: ReportCampaignOption[];
    can: { export: boolean };
};

function money(value: number): string {
    return `GHS ${value.toLocaleString('en-GH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

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
 * One row per campaign: how much of the audience moved, what failed and what
 * the campaign cost. Each row links to the campaign, where the recipient-level
 * outcomes live.
 */
export default function CampaignReport({
    filters,
    period,
    report,
    channels,
    campaigns,
    can,
}: PageProps) {
    const totals = report.totals;

    return (
        <>
            <Head title="Campaign report" />

            <div className="flex flex-col gap-4">
                <PageHeader
                    icon={Megaphone}
                    title="Campaigns"
                    description="Outcomes and spend for every campaign created in the window."
                    badge={
                        <span className="text-muted-foreground text-xs">
                            {period.label}
                        </span>
                    }
                    actions={
                        can.export ? (
                            <Button asChild variant="outline">
                                <Link
                                    href={`${urls.reports.exports.index}?report=campaigns&from=${filters.from}&to=${filters.to}`}
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
                            url={urls.reports.campaigns}
                            values={filters}
                            channels={channels}
                            campaigns={campaigns}
                        />
                    </CardContent>
                </Card>

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        title="Campaigns"
                        value={totals.campaigns}
                        description="Created in this window"
                        icon={Megaphone}
                        tone="brand"
                    />
                    <StatCard
                        title="Recipients"
                        value={totals.recipients}
                        description={`${totals.pending} still working`}
                        icon={Megaphone}
                        tone="signal"
                    />
                    <StatCard
                        title="Delivered"
                        value={totals.delivered}
                        description={
                            totals.delivery_rate === null
                                ? 'Nothing attempted yet'
                                : `${totals.delivery_rate}% of attempts`
                        }
                        icon={Megaphone}
                        tone="success"
                    />
                    <StatCard
                        title="Spend"
                        value={money(totals.spend)}
                        description={`${totals.failed} failed recipients`}
                        icon={Megaphone}
                        tone={totals.failed > 0 ? 'warning' : 'neutral'}
                    />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Campaign outcomes</CardTitle>
                        <CardDescription>
                            Delivery rate is confirmed deliveries over
                            everything attempted, including failures.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="px-0">
                        {report.rows.length === 0 ? (
                            <EmptyState
                                icon={Megaphone}
                                title="No campaigns in this window"
                                description="Change the dates or the filters to widen the search."
                            />
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="pl-4">
                                            Campaign
                                        </TableHead>
                                        <TableHead>Channel</TableHead>
                                        <TableHead>Status</TableHead>
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
                                        <TableHead className="pr-4">
                                            Launched
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {report.rows.map((row) => (
                                        <TableRow key={row.id}>
                                            <TableCell className="pl-4">
                                                <Link
                                                    href={urls.campaigns.show(
                                                        row.id,
                                                    )}
                                                    className="font-medium hover:underline"
                                                >
                                                    {row.name}
                                                </Link>
                                                <p className="text-muted-foreground text-xs">
                                                    {row.reference}
                                                </p>
                                            </TableCell>
                                            <TableCell>
                                                {row.channel_label}
                                            </TableCell>
                                            <TableCell>
                                                <StatusBadge
                                                    status={row.status}
                                                    label={row.status_label}
                                                />
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
                                            <TableCell className="text-muted-foreground pr-4 text-xs">
                                                {shortDate(row.launched_at)}
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

CampaignReport.layout = {
    breadcrumbs: [
        {
            title: 'Reports',
            href: urls.reports.index,
        },
        {
            title: 'Campaigns',
            href: urls.reports.campaigns,
        },
    ],
};
