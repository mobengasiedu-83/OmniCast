import { Head, router } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import PageHeader from '@/components/page-header';
import Pagination from '@/components/pagination';
import StatCard from '@/components/stat-card';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
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
import type { Paginated, SelectOption } from '@/types';

type AcceptanceRow = {
    id: number;
    document: string | null;
    document_slug: string | null;
    version: string | null;
    context: string;
    context_label: string;
    account: string | null;
    email: string | null;
    client: string | null;
    client_code: string | null;
    ip_address: string | null;
    accepted_at: string;
};

type PageProps = {
    acceptances: Paginated<AcceptanceRow>;
    filters: { document: string; context: string; search: string };
    stats: {
        total: number;
        last_30_days: number;
        organisations: number;
        documents: number;
    };
    documents: SelectOption[];
    contexts: SelectOption[];
};

/**
 * The acceptance register.
 *
 * Every row was written once and never edited, so the screen is read-only: the
 * only actions are narrowing the query and downloading the same query as a CSV
 * for a regulator, an auditor or a client.
 */
export default function LegalAcceptances({
    acceptances,
    filters,
    stats,
    documents,
    contexts,
}: PageProps) {
    const [search, setSearch] = useState(filters.search);
    const [document, setDocument] = useState(filters.document);
    const [context, setContext] = useState(filters.context);

    const query = (): Record<string, string | null> => ({
        search: search || null,
        document: document || null,
        context: context || null,
    });

    const apply = (event?: FormEvent): void => {
        event?.preventDefault();
        router.get(urls.admin.legal.acceptances.index, query(), {
            preserveState: true,
            replace: true,
        });
    };

    const exportHref = `${urls.admin.legal.acceptances.export}?${new URLSearchParams(
        Object.entries(query()).filter((entry): entry is [string, string] =>
            Boolean(entry[1]),
        ),
    ).toString()}`;

    return (
        <>
            <Head title="Acceptance register" />

            <div className="grid gap-6">
                <PageHeader
                    title="Acceptance register"
                    description="Who accepted which version of which policy, when and from where."
                    actions={
                        <Button variant="outline" asChild>
                            <a href={exportHref}>
                                <Download />
                                Export CSV
                            </a>
                        </Button>
                    }
                />

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard title="Acceptances" value={stats.total} />
                    <StatCard title="Last 30 days" value={stats.last_30_days} />
                    <StatCard title="Organisations" value={stats.organisations} />
                    <StatCard title="Documents" value={stats.documents} />
                </div>

                <RegistryFilters
                    search={search}
                    document={document}
                    context={context}
                    documents={documents}
                    contexts={contexts}
                    onSearch={setSearch}
                    onDocument={setDocument}
                    onContext={setContext}
                    onApply={apply}
                />

                <Card>
                    <CardContent className="p-0">
                        <AcceptanceTable rows={acceptances} />
                    </CardContent>
                </Card>

                <Pagination paginator={acceptances} noun="acceptances" />
            </div>
        </>
    );
}

LegalAcceptances.layout = {
    breadcrumbs: [
        {
            title: 'Acceptance register',
            href: urls.admin.legal.acceptances.index,
        },
    ],
};

/**
 * The search box and the two narrowing selects.
 */
function RegistryFilters({
    search,
    document,
    context,
    documents,
    contexts,
    onSearch,
    onDocument,
    onContext,
    onApply,
}: {
    search: string;
    document: string;
    context: string;
    documents: SelectOption[];
    contexts: SelectOption[];
    onSearch: (value: string) => void;
    onDocument: (value: string) => void;
    onContext: (value: string) => void;
    onApply: (event: FormEvent) => void;
}) {
    return (
        <form className="flex flex-wrap items-center gap-2" onSubmit={onApply}>
            <Input
                value={search}
                onChange={(event) => onSearch(event.target.value)}
                placeholder="Search account, email, organisation"
                className="max-w-xs"
            />

            <Select
                value={document || 'all'}
                onValueChange={(value) =>
                    onDocument(value === 'all' ? '' : value)
                }
            >
                <SelectTrigger className="w-56">
                    <SelectValue placeholder="All documents" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All documents</SelectItem>
                    {documents.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            <Select
                value={context || 'all'}
                onValueChange={(value) =>
                    onContext(value === 'all' ? '' : value)
                }
            >
                <SelectTrigger className="w-52">
                    <SelectValue placeholder="All screens" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All screens</SelectItem>
                    {contexts.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            <Button type="submit" variant="outline">
                Filter
            </Button>
        </form>
    );
}

/**
 * One page of the register.
 */
function AcceptanceTable({ rows }: { rows: Paginated<AcceptanceRow> }) {
    return (
        <>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Document</TableHead>
                        <TableHead>Version</TableHead>
                        <TableHead>Organisation</TableHead>
                        <TableHead>Account</TableHead>
                        <TableHead>Where</TableHead>
                        <TableHead>When</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {rows.data.map((acceptance) => (
                        <TableRow key={acceptance.id}>
                            <TableCell className="font-medium">
                                {acceptance.document}
                            </TableCell>
                            <TableCell>{acceptance.version}</TableCell>
                            <TableCell>
                                {acceptance.client ?? '—'}
                                {acceptance.client_code && (
                                    <p className="text-xs text-muted-foreground">
                                        {acceptance.client_code}
                                    </p>
                                )}
                            </TableCell>
                            <TableCell>
                                {acceptance.account ?? '—'}
                                <p className="text-xs text-muted-foreground">
                                    {acceptance.email ?? '—'}
                                </p>
                            </TableCell>
                            <TableCell>{acceptance.context_label}</TableCell>
                            <TableCell className="text-xs whitespace-nowrap text-muted-foreground">
                                {new Date(acceptance.accepted_at).toLocaleString()}
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>

            {rows.data.length === 0 && (
                <p className="p-8 text-center text-sm text-muted-foreground">
                    No acceptance matches these filters.
                </p>
            )}
        </>
    );
}
