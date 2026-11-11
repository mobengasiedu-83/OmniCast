import { Head, router, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import PageHeader from '@/components/page-header';
import Pagination from '@/components/pagination';
import StatCard from '@/components/stat-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
import {
    downloadEvidence,
    storeEvidence,
} from '@/actions/App/Http/Controllers/Admin/OrganisationDpaController';
import { badgeTone } from '@/lib/tone';
import { urls } from '@/lib/urls';
import type { Paginated, SelectOption } from '@/types';

type DpaRow = {
    client_id: number;
    client: string;
    code: string;
    status: string;
    status_label: string;
    status_tone: string;
    version: string | null;
    signatory_name: string | null;
    signatory_email: string | null;
    requested_at: string | null;
    signed_at: string | null;
    notes: string | null;
    evidence: {
        id: number;
        name: string;
        size_bytes: number;
        sha256: string;
        uploaded_at: string | null;
        uploaded_by: string | null;
    }[];
};

type PageProps = {
    clients: Paginated<DpaRow>;
    filters: { status: string; search: string };
    statuses: SelectOption[];
    stats: {
        signed: number;
        awaiting: number;
        declined: number;
        without: number;
    };
    document: { url: string; title: string } | null;
};

/**
 * Data processing agreements, one row per organisation.
 *
 * The list starts from the client accounts rather than from the agreement rows,
 * so an organisation that has never asked for one is visible — that gap is the
 * reason the screen exists.
 */
export default function LegalDpas({
    clients,
    filters,
    statuses,
    stats,
    document,
}: PageProps) {
    const [search, setSearch] = useState(filters.search);
    const [status, setStatus] = useState(filters.status);

    const apply = (nextStatus: string = status, nextSearch: string = search): void => {
        router.get(
            urls.admin.legal.dpas.index,
            { search: nextSearch || null, status: nextStatus || null },
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Data processing agreements" />

            <div className="grid gap-6">
                <PageHeader
                    title="Data processing agreements"
                    description="Track which organisations have signed the controller-processor agreement."
                    actions={
                        document ? (
                            <Button variant="outline" asChild>
                                <a
                                    href={document.url}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    Read the DPA
                                </a>
                            </Button>
                        ) : undefined
                    }
                />

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard title="Signed" value={stats.signed} />
                    <StatCard title="In progress" value={stats.awaiting} />
                    <StatCard title="Declined" value={stats.declined} />
                    <StatCard title="No agreement" value={stats.without} />
                </div>

                <DpaFilters
                    search={search}
                    status={status}
                    statuses={statuses}
                    onSearch={setSearch}
                    onStatus={(value) => {
                        setStatus(value);
                        apply(value);
                    }}
                    onApply={() => apply()}
                />

                <Card>
                    <CardContent className="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Organisation</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Signatory</TableHead>
                                    <TableHead>Signed</TableHead>
                                    <TableHead className="text-right">
                                        Record
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {clients.data.map((row) => (
                                    <DpaRowForm
                                        key={row.client_id}
                                        row={row}
                                        statuses={statuses}
                                    />
                                ))}
                            </TableBody>
                        </Table>

                        {clients.data.length === 0 && (
                            <p className="p-8 text-center text-sm text-muted-foreground">
                                No organisation matches these filters.
                            </p>
                        )}
                    </CardContent>
                </Card>

                <Pagination paginator={clients} noun="organisations" />
            </div>
        </>
    );
}

LegalDpas.layout = {
    breadcrumbs: [
        {
            title: 'Data processing agreements',
            href: urls.admin.legal.dpas.index,
        },
    ],
};

function DpaFilters({
    search,
    status,
    statuses,
    onSearch,
    onStatus,
    onApply,
}: {
    search: string;
    status: string;
    statuses: SelectOption[];
    onSearch: (value: string) => void;
    onStatus: (value: string) => void;
    onApply: () => void;
}) {
    return (
        <form
            className="flex flex-wrap items-center gap-2"
            onSubmit={(event: FormEvent) => {
                event.preventDefault();
                onApply();
            }}
        >
            <Input
                value={search}
                onChange={(event) => onSearch(event.target.value)}
                placeholder="Search organisation"
                className="max-w-xs"
            />
            <Select
                value={status || 'all'}
                onValueChange={(value) =>
                    onStatus(value === 'all' ? '' : value)
                }
            >
                <SelectTrigger className="w-56">
                    <SelectValue placeholder="Any status" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">Any status</SelectItem>
                    {statuses.map((option) => (
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
 * One organisation, with the form to record where its agreement stands.
 */
function DpaRowForm({
    row,
    statuses,
}: {
    row: DpaRow;
    statuses: SelectOption[];
}) {
    const [editing, setEditing] = useState(false);
    const form = useForm({
        status: row.status,
        signatory_name: row.signatory_name ?? '',
        signatory_email: row.signatory_email ?? '',
        signed_at: row.signed_at ? row.signed_at.slice(0, 10) : '',
        notes: row.notes ?? '',
    });
    const evidenceForm = useForm<{ document: File | null }>({
        document: null,
    });

    const submit = (event: FormEvent): void => {
        event.preventDefault();
        form.put(urls.admin.legal.dpas.update(row.client_id), {
            preserveScroll: true,
            onSuccess: () => setEditing(false),
        });
    };

    const uploadEvidence = (event: FormEvent): void => {
        event.preventDefault();
        evidenceForm.post(
            storeEvidence.url(row.client_id),
            {
                preserveScroll: true,
                onSuccess: () => evidenceForm.reset(),
            },
        );
    };

    return (
        <>
            <TableRow>
                <TableCell className="font-medium">
                    {row.client}
                    <p className="text-xs text-muted-foreground">{row.code}</p>
                </TableCell>
                <TableCell>
                    <Badge variant={badgeTone(row.status_tone)}>
                        {row.status_label}
                    </Badge>
                    {row.version && (
                        <p className="text-xs text-muted-foreground">
                            {row.version}
                        </p>
                    )}
                </TableCell>
                <TableCell>
                    {row.signatory_name ?? '—'}
                    <p className="text-xs text-muted-foreground">
                        {row.signatory_email ?? '—'}
                    </p>
                </TableCell>
                <TableCell className="text-xs text-muted-foreground">
                    {row.signed_at
                        ? new Date(row.signed_at).toLocaleDateString()
                        : '—'}
                    {row.evidence.length > 0 && (
                        <p>{row.evidence.length} private evidence file(s)</p>
                    )}
                </TableCell>
                <TableCell className="text-right">
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => setEditing((open) => !open)}
                    >
                        {editing ? 'Close' : 'Record'}
                    </Button>
                </TableCell>
            </TableRow>

            {editing && (
                <TableRow>
                    <TableCell colSpan={5}>
                        <form
                            className="grid gap-3 rounded-lg bg-muted/40 p-4 sm:grid-cols-2"
                            onSubmit={submit}
                        >
                            <div className="grid gap-1.5">
                                <Label htmlFor={`status-${row.client_id}`}>
                                    Status
                                </Label>
                                <Select
                                    value={form.data.status}
                                    onValueChange={(value) =>
                                        form.setData('status', value)
                                    }
                                >
                                    <SelectTrigger
                                        id={`status-${row.client_id}`}
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {statuses.map((option) => (
                                            <SelectItem
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {form.errors.status && (
                                    <p className="text-xs text-destructive">
                                        {form.errors.status}
                                    </p>
                                )}
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor={`signed-${row.client_id}`}>
                                    Signed on
                                </Label>
                                <Input
                                    id={`signed-${row.client_id}`}
                                    type="date"
                                    value={form.data.signed_at}
                                    onChange={(event) =>
                                        form.setData(
                                            'signed_at',
                                            event.target.value,
                                        )
                                    }
                                />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor={`name-${row.client_id}`}>
                                    Signatory name
                                </Label>
                                <Input
                                    id={`name-${row.client_id}`}
                                    value={form.data.signatory_name}
                                    onChange={(event) =>
                                        form.setData(
                                            'signatory_name',
                                            event.target.value,
                                        )
                                    }
                                />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor={`email-${row.client_id}`}>
                                    Signatory email
                                </Label>
                                <Input
                                    id={`email-${row.client_id}`}
                                    type="email"
                                    value={form.data.signatory_email}
                                    onChange={(event) =>
                                        form.setData(
                                            'signatory_email',
                                            event.target.value,
                                        )
                                    }
                                />
                            </div>

                            <div className="grid gap-1.5 sm:col-span-2">
                                <Label htmlFor={`notes-${row.client_id}`}>
                                    Notes
                                </Label>
                                <Input
                                    id={`notes-${row.client_id}`}
                                    value={form.data.notes}
                                    onChange={(event) =>
                                        form.setData('notes', event.target.value)
                                    }
                                    placeholder="Reference number, exchange date, anything the account should see."
                                />
                            </div>

                            <div className="flex gap-2 sm:col-span-2">
                                <Button
                                    type="submit"
                                    size="sm"
                                    disabled={form.processing}
                                >
                                    Save
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    onClick={() => setEditing(false)}
                                >
                                    Cancel
                                </Button>
                            </div>
                        </form>
                        {row.status === 'signed' && (
                            <div className="grid gap-3 border-t border-border/60 px-4 py-4">
                                <div>
                                    <p className="text-sm font-medium">
                                        Signed agreement evidence
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        PDF files are append-only and stored on
                                        the configured private disk.
                                    </p>
                                </div>
                                {row.evidence.length > 0 && (
                                    <ul className="grid gap-2">
                                        {row.evidence.map((evidence) => (
                                            <li
                                                key={evidence.id}
                                                className="flex flex-wrap items-center justify-between gap-2 rounded-md border border-border/60 p-3 text-xs"
                                            >
                                                <div>
                                                    <p className="font-medium">
                                                        {evidence.name}
                                                    </p>
                                                    <p className="text-muted-foreground">
                                                        SHA-256{' '}
                                                        <span className="font-mono">
                                                            {evidence.sha256}
                                                        </span>
                                                    </p>
                                                </div>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <a
                                                        href={downloadEvidence.url(
                                                            {
                                                                client: row.client_id,
                                                                evidence:
                                                                    evidence.id,
                                                            },
                                                        )}
                                                    >
                                                        Download
                                                    </a>
                                                </Button>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                                <form
                                    className="flex flex-wrap items-end gap-3"
                                    onSubmit={uploadEvidence}
                                >
                                    <div className="grid gap-1.5">
                                        <Label
                                            htmlFor={`evidence-${row.client_id}`}
                                        >
                                            Signed DPA PDF (max 20 MB)
                                        </Label>
                                        <Input
                                            id={`evidence-${row.client_id}`}
                                            type="file"
                                            accept="application/pdf,.pdf"
                                            onChange={(event) =>
                                                evidenceForm.setData(
                                                    'document',
                                                    event.target.files?.[0] ??
                                                        null,
                                                )
                                            }
                                        />
                                        {evidenceForm.errors.document && (
                                            <p className="text-xs text-destructive">
                                                {evidenceForm.errors.document}
                                            </p>
                                        )}
                                    </div>
                                    <Button
                                        type="submit"
                                        size="sm"
                                        disabled={evidenceForm.processing}
                                    >
                                        Upload evidence
                                    </Button>
                                </form>
                            </div>
                        )}
                    </TableCell>
                </TableRow>
            )}
        </>
    );
}
