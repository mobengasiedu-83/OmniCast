import { Head } from '@inertiajs/react';
import { Download } from 'lucide-react';
import PageHeader from '@/components/page-header';
import StatCard from '@/components/stat-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { urls } from '@/lib/urls';

type DocumentReport = {
    slug: string;
    title: string;
    is_active: boolean;
    version: string | null;
    effective_on: string | null;
    acceptances: number;
    accounts: number;
    coverage: number;
    requires: { registration: boolean; campaign: boolean; top_up: boolean };
};

type PageProps = {
    documents: DocumentReport[];
    contexts: { value: string; label: string; total: number }[];
    stats: {
        acceptances: number;
        accounts_covered: number;
        active_accounts: number;
        last_accepted_at: string | null;
    };
};

/**
 * The compliance reading: how much of the estate has accepted what is live.
 *
 * Coverage is measured against active client accounts, the population a
 * regulator would expect a policy to reach. The register itself is immutable, so
 * this screen only reads and links to the same query as a CSV download.
 */
export default function ComplianceReports({
    documents,
    contexts,
    stats,
}: PageProps) {
    return (
        <>
            <Head title="Compliance reports" />

            <div className="grid gap-6">
                <PageHeader
                    title="Compliance reports"
                    description="Acceptance coverage per policy, and the downloads an audit will ask for."
                    actions={
                        <Button variant="outline" asChild>
                            <a href={urls.admin.legal.acceptances.export}>
                                <Download />
                                Export every acceptance
                            </a>
                        </Button>
                    }
                />

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        title="Acceptances recorded"
                        value={stats.acceptances}
                    />
                    <StatCard
                        title="Accounts covered"
                        value={stats.accounts_covered}
                    />
                    <StatCard
                        title="Active client accounts"
                        value={stats.active_accounts}
                    />
                    <StatCard
                        title="Most recent acceptance"
                        value={
                            stats.last_accepted_at
                                ? new Date(
                                      stats.last_accepted_at,
                                  ).toLocaleDateString()
                                : '—'
                        }
                    />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Acceptance coverage</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Document</TableHead>
                                    <TableHead>Live version</TableHead>
                                    <TableHead>Asked at</TableHead>
                                    <TableHead>Acceptances</TableHead>
                                    <TableHead>Accounts</TableHead>
                                    <TableHead className="w-32">
                                        Coverage
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {documents.map((document) => (
                                    <TableRow key={document.slug}>
                                        <TableCell className="font-medium">
                                            {document.title}
                                            {!document.is_active && (
                                                <Badge
                                                    variant="muted"
                                                    className="ml-1.5"
                                                >
                                                    Withdrawn
                                                </Badge>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-xs text-muted-foreground">
                                            {document.version ??
                                                'Not published'}
                                            {document.effective_on
                                                ? ` · ${document.effective_on}`
                                                : ''}
                                        </TableCell>
                                        <TableCell className="text-xs text-muted-foreground">
                                            {[
                                                document.requires
                                                    .registration &&
                                                    'Application',
                                                document.requires.campaign &&
                                                    'Campaign',
                                                document.requires.top_up &&
                                                    'Top-up',
                                            ]
                                                .filter(Boolean)
                                                .join(', ') || '—'}
                                        </TableCell>
                                        <TableCell>
                                            {document.acceptances}
                                        </TableCell>
                                        <TableCell>
                                            {document.accounts}
                                        </TableCell>
                                        <TableCell>
                                            {document.coverage}%
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>

                        {documents.length === 0 && (
                            <p className="p-8 text-center text-sm text-muted-foreground">
                                No policy documents exist yet.
                            </p>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Acceptances by screen</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Screen</TableHead>
                                    <TableHead className="w-32">
                                        Acceptances
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {contexts.map((context) => (
                                    <TableRow key={context.value}>
                                        <TableCell className="font-medium">
                                            {context.label}
                                        </TableCell>
                                        <TableCell>{context.total}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

ComplianceReports.layout = {
    breadcrumbs: [
        {
            title: 'Compliance reports',
            href: urls.admin.legal.reports,
        },
    ],
};
