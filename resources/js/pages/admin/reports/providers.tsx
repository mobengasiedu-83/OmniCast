import { Head } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCheck,
    Coins,
    Mail,
    MessageSquareText,
    PhoneCall,
    Scale,
    ServerCog,
} from 'lucide-react';
import EmptyState from '@/components/empty-state';
import PageHeader from '@/components/page-header';
import ReportFilters from '@/components/report-filters';
import StatCard from '@/components/stat-card';
import { Badge } from '@/components/ui/badge';
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
    LedgerReconciliation,
    ProviderChannelReport,
} from '@/types';

type PageProps = {
    filters: {
        from: string;
        to: string;
        client_id: number | null;
    };
    period: { label: string; days: number };
    sms: ProviderChannelReport;
    voice: ProviderChannelReport;
    email: ProviderChannelReport;
    reconciliation: LedgerReconciliation;
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
 * How every transport performed, and whether the three ledgers that back the
 * spend figure still agree.
 *
 * The reconciliation panel is the point of this screen: a provider that looks
 * healthy while the campaign and wallet ledgers disagree is exactly the case an
 * operator needs to see.
 */
export default function AdminProviderReport({
    filters,
    period,
    sms,
    voice,
    email,
    reconciliation,
    clients,
}: PageProps) {
    const channels = [
        { key: 'sms', report: sms, icon: MessageSquareText },
        { key: 'voice', report: voice, icon: PhoneCall },
        { key: 'email', report: email, icon: Mail },
    ];

    return (
        <>
            <Head title="Provider performance" />

            <div className="flex flex-col gap-4">
                <PageHeader
                    icon={ServerCog}
                    title="Provider performance"
                    description="Delivery by transport, plus the ledger check that proves the spend figure is real."
                    badge={
                        <span className="text-muted-foreground text-xs">
                            {period.label}
                        </span>
                    }
                />

                <Card className="py-4">
                    <CardContent>
                        <ReportFilters
                            url={urls.admin.reports.providers}
                            values={filters}
                            clients={clients}
                        />
                    </CardContent>
                </Card>

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        title="Reconciled"
                        value={reconciliation.balanced ? 'Yes' : 'No'}
                        description={
                            reconciliation.balanced
                                ? `Within ${money(reconciliation.tolerance)}`
                                : `${money(reconciliation.variance)} variance`
                        }
                        icon={reconciliation.balanced ? CheckCheck : AlertTriangle}
                        tone={reconciliation.balanced ? 'success' : 'danger'}
                    />
                    <StatCard
                        title="Campaign cost"
                        value={money(reconciliation.campaign_cost)}
                        description="Closed campaigns in the window"
                        icon={Coins}
                        tone="brand"
                    />
                    <StatCard
                        title="Charged"
                        value={money(reconciliation.ledger)}
                        description="Debited from the wallets"
                        icon={Scale}
                        tone="signal"
                    />
                    <StatCard
                        title="Mismatches"
                        value={reconciliation.campaigns.length}
                        description="Campaigns where the ledgers disagree"
                        icon={AlertTriangle}
                        tone={
                            reconciliation.campaigns.length > 0
                                ? 'warning'
                                : 'neutral'
                        }
                    />
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    {channels.map(({ key, report, icon: Icon }) => (
                        <Card key={key}>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Icon className="text-muted-foreground size-4" />
                                    {report.label}
                                </CardTitle>
                                <CardDescription>
                                    {report.totals.total.toLocaleString()}{' '}
                                    attempt(s)
                                    {report.totals.delivery_rate === null
                                        ? ''
                                        : ` · ${report.totals.delivery_rate}% delivered`}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="px-0">
                                {report.rows.length === 0 ? (
                                    <EmptyState
                                        icon={Icon}
                                        title="No traffic"
                                        description="Nothing was sent through this channel in the window."
                                    />
                                ) : (
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead className="pl-6">
                                                    Provider
                                                </TableHead>
                                                <TableHead className="text-right">
                                                    Sent
                                                </TableHead>
                                                <TableHead className="text-right">
                                                    Failed
                                                </TableHead>
                                                <TableHead className="pr-6 text-right">
                                                    Rate
                                                </TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {report.rows.map((row) => (
                                                <TableRow key={row.provider}>
                                                    <TableCell className="pl-6 font-medium capitalize">
                                                        {row.provider || '—'}
                                                    </TableCell>
                                                    <TableCell className="text-right tabular-nums">
                                                        {row.total.toLocaleString()}
                                                    </TableCell>
                                                    <TableCell className="text-right tabular-nums">
                                                        {row.failed.toLocaleString()}
                                                    </TableCell>
                                                    <TableCell className="pr-6 text-right tabular-nums">
                                                        {row.delivery_rate === null
                                                            ? '—'
                                                            : `${row.delivery_rate}%`}
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                )}
                            </CardContent>
                        </Card>
                    ))}
                </div>


                <Card>
                    <CardHeader className="flex flex-row items-start justify-between gap-3">
                        <div className="space-y-1">
                            <CardTitle>Ledger reconciliation</CardTitle>
                            <CardDescription>
                                Event cost, campaign cost and wallet debits must
                                agree before a spend figure is published.
                            </CardDescription>
                        </div>
                        <Badge
                            variant={
                                reconciliation.balanced
                                    ? 'success'
                                    : 'destructive'
                            }
                        >
                            {reconciliation.balanced ? 'Balanced' : 'Variance'}
                        </Badge>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            {(
                                [
                                    ['SMS usage', reconciliation.usage.sms],
                                    ['Voice usage', reconciliation.usage.voice],
                                    ['Email usage', reconciliation.usage.email],
                                    ['Total usage', reconciliation.usage.total],
                                ] as const
                            ).map(([label, value]) => (
                                <div
                                    key={label}
                                    className="bg-muted/40 rounded-lg p-3"
                                >
                                    <p className="text-muted-foreground text-[11px] font-semibold tracking-[0.14em] uppercase">
                                        {label}
                                    </p>
                                    <p className="mt-1 text-sm font-semibold tabular-nums">
                                        {money(value)}
                                    </p>
                                </div>
                            ))}
                        </div>

                        <div className="grid gap-3 sm:grid-cols-2">
                            <div className="bg-muted/40 rounded-lg p-3">
                                <p className="text-muted-foreground text-[11px] font-semibold tracking-[0.14em] uppercase">
                                    Usage vs campaign
                                </p>
                                <p className="mt-1 text-sm font-semibold tabular-nums">
                                    {money(reconciliation.usage_variance)}
                                </p>
                            </div>
                            <div className="bg-muted/40 rounded-lg p-3">
                                <p className="text-muted-foreground text-[11px] font-semibold tracking-[0.14em] uppercase">
                                    Campaign vs wallet
                                </p>
                                <p className="mt-1 text-sm font-semibold tabular-nums">
                                    {money(reconciliation.variance)}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>


                <Card>
                    <CardHeader>
                        <CardTitle>Campaigns that disagree</CardTitle>
                        <CardDescription>
                            The first ten campaigns whose claimed cost does not
                            match what the wallet was debited.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="px-0">
                        {reconciliation.campaigns.length === 0 ? (
                            <EmptyState
                                icon={CheckCheck}
                                title="Every ledger agrees"
                                description="No campaign in this window has a variance above tolerance."
                            />
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="pl-6">
                                            Reference
                                        </TableHead>
                                        <TableHead>Campaign</TableHead>
                                        <TableHead className="text-right">
                                            Claimed
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Debited
                                        </TableHead>
                                        <TableHead className="pr-6 text-right">
                                            Variance
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {reconciliation.campaigns.map((campaign) => (
                                        <TableRow key={campaign.id}>
                                            <TableCell className="pl-6 font-mono text-xs">
                                                {campaign.reference}
                                            </TableCell>
                                            <TableCell className="font-medium">
                                                {campaign.name}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {money(campaign.claimed)}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {money(campaign.debited)}
                                            </TableCell>
                                            <TableCell className="text-destructive pr-6 text-right font-medium tabular-nums">
                                                {money(campaign.variance)}
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

AdminProviderReport.layout = {
    breadcrumbs: [
        {
            title: 'Provider performance',
            href: urls.admin.reports.providers,
        },
    ],
};

