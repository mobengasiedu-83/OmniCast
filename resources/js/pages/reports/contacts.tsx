import { Head, Link } from '@inertiajs/react';
import { Download, Users } from 'lucide-react';
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
import type { ContactOutcomeReport, ReportPeriod } from '@/types';

type PageProps = {
    filters: { from: string; to: string };
    period: ReportPeriod;
    report: ContactOutcomeReport;
    can: { export: boolean };
};

/**
 * How the audience answered: who can still be reached, who keeps failing and
 * who asked to stop.
 */
export default function ContactReport({
    filters,
    period,
    report,
    can,
}: PageProps) {
    const totals = report.totals;

    return (
        <>
            <Head title="Contact report" />

            <div className="flex flex-col gap-4">
                <PageHeader
                    icon={Users}
                    title="Contacts"
                    description="Reachability and recipient outcomes for the tenant's audience."
                    badge={
                        <span className="text-muted-foreground text-xs">
                            {period.label}
                        </span>
                    }
                    actions={
                        can.export ? (
                            <Button asChild variant="outline">
                                <Link
                                    href={`${urls.reports.exports.index}?report=contacts&from=${filters.from}&to=${filters.to}`}
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
                            url={urls.reports.contacts}
                            values={filters}
                        />
                    </CardContent>
                </Card>

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        title="Contacts"
                        value={totals.contacts}
                        description={`${totals.reachable} reachable`}
                        icon={Users}
                        tone="brand"
                    />
                    <StatCard
                        title="Reached"
                        value={totals.reached}
                        description="Distinct recipients in this window"
                        icon={Users}
                        tone="signal"
                    />
                    <StatCard
                        title="Delivered"
                        value={totals.delivered}
                        description={`${totals.failed} failed`}
                        icon={Users}
                        tone={totals.failed > 0 ? 'warning' : 'success'}
                    />
                    <StatCard
                        title="Opted out"
                        value={totals.opt_outs}
                        description={`${totals.dnd} on do not disturb`}
                        icon={Users}
                        tone={totals.opt_outs > 0 ? 'danger' : 'neutral'}
                    />
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Recipient outcomes</CardTitle>
                            <CardDescription>
                                Every recipient that entered a campaign in this
                                window, by outcome.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="px-0">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="pl-4">
                                            Outcome
                                        </TableHead>
                                        <TableHead className="pr-4 text-right">
                                            Recipients
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {report.byStatus.map((row) => (
                                        <TableRow key={row.status}>
                                            <TableCell className="pl-4">
                                                <StatusBadge
                                                    status={row.status}
                                                    label={row.label}
                                                />
                                            </TableCell>
                                            <TableCell className="pr-4 text-right tabular-nums">
                                                {row.total.toLocaleString()}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Most failures</CardTitle>
                            <CardDescription>
                                Contacts whose recipients failed most often.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="px-0">
                            {report.topContacts.length === 0 ? (
                                <EmptyState
                                    icon={Users}
                                    title="No recipients in this window"
                                    description="Recipients appear here once a campaign dispatches to them."
                                />
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="pl-4">
                                                Contact
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Recipients
                                            </TableHead>
                                            <TableHead className="pr-4 text-right">
                                                Failed
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {report.topContacts.map((row) => (
                                            <TableRow key={row.contact_id}>
                                                <TableCell className="pl-4">
                                                    <p className="font-medium">
                                                        {row.name}
                                                    </p>
                                                    <p className="text-muted-foreground text-xs">
                                                        {row.phone ??
                                                            row.email ??
                                                            '—'}
                                                    </p>
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {row.recipients}
                                                </TableCell>
                                                <TableCell className="pr-4 text-right tabular-nums">
                                                    {row.failed}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Recent opt-outs</CardTitle>
                        <CardDescription>
                            Contacts that asked to stop in this window.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="px-0">
                        {report.recentOptOuts.length === 0 ? (
                            <EmptyState
                                icon={Users}
                                title="Nobody opted out"
                                description="Opt-outs appear here when a contact asks to stop or a voice IVR digit does it for them."
                            />
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="pl-4">
                                            Contact
                                        </TableHead>
                                        <TableHead>Phone</TableHead>
                                        <TableHead className="pr-4">
                                            Opted out
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {report.recentOptOuts.map((row) => (
                                        <TableRow key={row.id}>
                                            <TableCell className="pl-4 font-medium">
                                                {row.name}
                                            </TableCell>
                                            <TableCell>
                                                {row.phone ?? '—'}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground pr-4 text-xs">
                                                {row.dnd_at
                                                    ? new Date(
                                                          row.dnd_at,
                                                      ).toLocaleString()
                                                    : '—'}
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

ContactReport.layout = {
    breadcrumbs: [
        {
            title: 'Reports',
            href: urls.reports.index,
        },
        {
            title: 'Contacts',
            href: urls.reports.contacts,
        },
    ],
};
