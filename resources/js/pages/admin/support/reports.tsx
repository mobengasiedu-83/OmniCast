import { Head, Link } from '@inertiajs/react';
import { BarChart3, Clock, Inbox, LifeBuoy, Timer } from 'lucide-react';
import PageHeader from '@/components/page-header';
import StatCard from '@/components/stat-card';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { index as knowledgeBase } from '@/routes/admin/support/knowledge-base';

type Count = { name: string; total: number };

type PageProps = {
    totals: {
        enquiries: number;
        tickets: number;
        open: number;
        unanswered: number;
    };
    window: {
        days: number;
        first_response_hours: number | null;
        resolution_hours: number | null;
    };
    by_status: { value: string; label: string; total: number }[];
    by_team: Count[];
    by_category: Count[];
    articles: {
        title: string;
        slug: string;
        views: number;
        is_published: boolean;
    }[];
};

/**
 * Support reports.
 *
 * The two numbers a support desk is judged on are here: how long a requester waits
 * for a first answer, and how long a conversation stays open. Both are averaged over
 * the last thirty days, and every breakdown is a plain count so nothing has to be
 * interpreted.
 */
export default function SupportReports({
    totals,
    window,
    by_status,
    by_team,
    by_category,
    articles,
}: PageProps) {
    const hours = (value: number | null): string =>
        value === null ? '—' : `${value} h`;

    return (
        <>
            <Head title="Support reports" />

            <div className="grid gap-6">
                <PageHeader
                    icon={BarChart3}
                    title="Support reports"
                    description="Volumes, response times and what the library is actually being read for."
                />

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        title="Enquiries"
                        value={totals.enquiries}
                        icon={Inbox}
                        tone="brand"
                        description="From the public form"
                    />
                    <StatCard
                        title="Tickets"
                        value={totals.tickets}
                        icon={LifeBuoy}
                        tone="signal"
                        description="From client accounts"
                    />
                    <StatCard
                        title="Open now"
                        value={totals.open}
                        icon={Timer}
                        tone="warning"
                        description="New, open or waiting"
                    />
                    <StatCard
                        title="Unanswered"
                        value={totals.unanswered}
                        icon={Clock}
                        tone="danger"
                        description="Nobody has replied yet"
                    />
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm">
                                Response times (last {window.days} days)
                            </CardTitle>
                        </CardHeader>

                        <CardContent className="grid gap-3 sm:grid-cols-2">
                            <div className="rounded-lg border border-border/70 p-4">
                                <p className="text-[11px] font-semibold tracking-[0.14em] text-muted-foreground uppercase">
                                    First response
                                </p>
                                <p className="mt-1 text-2xl font-semibold tabular-nums">
                                    {hours(window.first_response_hours)}
                                </p>
                            </div>

                            <div className="rounded-lg border border-border/70 p-4">
                                <p className="text-[11px] font-semibold tracking-[0.14em] text-muted-foreground uppercase">
                                    Resolution
                                </p>
                                <p className="mt-1 text-2xl font-semibold tabular-nums">
                                    {hours(window.resolution_hours)}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm">
                                By status (last {window.days} days)
                            </CardTitle>
                        </CardHeader>

                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">
                                            Conversations
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {by_status.map((row) => (
                                        <TableRow key={row.value}>
                                            <TableCell>{row.label}</TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {row.total}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm">
                                By team (last {window.days} days)
                            </CardTitle>
                        </CardHeader>

                        <CardContent>
                            <CountTable rows={by_team} emptyLabel="team" />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm">
                                By category (last {window.days} days)
                            </CardTitle>
                        </CardHeader>

                        <CardContent>
                            <CountTable
                                rows={by_category}
                                emptyLabel="category"
                            />
                        </CardContent>
                    </Card>
                </div>
                <Card>
                    <CardHeader className="flex-row items-center justify-between">
                        <CardTitle className="text-sm">
                            Most-read articles
                        </CardTitle>
                        <Link
                            href={knowledgeBase()}
                            className="text-xs font-medium text-brand hover:underline"
                        >
                            Open the library
                        </Link>
                    </CardHeader>

                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Article</TableHead>
                                    <TableHead>State</TableHead>
                                    <TableHead className="text-right">
                                        Reads
                                    </TableHead>
                                </TableRow>
                            </TableHeader>

                            <TableBody>
                                {articles.map((article) => (
                                    <TableRow key={article.slug}>
                                        <TableCell>{article.title}</TableCell>
                                        <TableCell>
                                            {article.is_published ? (
                                                <Badge variant="success">
                                                    Published
                                                </Badge>
                                            ) : (
                                                <Badge variant="muted">
                                                    Draft
                                                </Badge>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {article.views}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>

                        {articles.length === 0 && (
                            <p className="p-6 text-center text-sm text-muted-foreground">
                                No article has been read yet.
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

/**
 * A two-column count table, used for the team and category breakdowns.
 */
function CountTable({ rows, emptyLabel }: { rows: Count[]; emptyLabel: string }) {
    if (rows.length === 0) {
        return (
            <p className="p-4 text-center text-sm text-muted-foreground">
                No {emptyLabel} has been configured yet.
            </p>
        );
    }

    return (
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead className="capitalize">{emptyLabel}</TableHead>
                    <TableHead className="text-right">Conversations</TableHead>
                </TableRow>
            </TableHeader>

            <TableBody>
                {rows.map((row) => (
                    <TableRow key={row.name}>
                        <TableCell>{row.name}</TableCell>
                        <TableCell className="text-right tabular-nums">
                            {row.total}
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}

