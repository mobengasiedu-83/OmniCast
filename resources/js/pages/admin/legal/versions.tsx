import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Send, Trash2 } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { useConfirm } from '@/components/confirm-dialog';
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
import { badgeTone } from '@/lib/tone';
import { urls } from '@/lib/urls';
import type { Paginated, SelectOption } from '@/types';

type VersionRow = {
    id: number;
    document: string | null;
    document_slug: string | null;
    version: string;
    status: string;
    status_label: string;
    status_tone: string;
    change_summary: string | null;
    effective_on: string | null;
    published_at: string | null;
    notified_at: string | null;
    requires_reacceptance: boolean;
    acceptances_count: number;
    notifications_count: number;
    can_edit: boolean;
    can_delete: boolean;
    body: string | null;
};

type PageProps = {
    versions: Paginated<VersionRow>;
    filters: { document: string; status: string };
    documents: SelectOption[];
    statuses: SelectOption[];
    stats: {
        versions: number;
        published: number;
        drafts: number;
        archived: number;
    };
};

/**
 * Every version of every policy in one list.
 *
 * The documents screen is organised around a document; this one is organised
 * around the wording, which is what an audit asks for: what was live on a date,
 * what replaced it, and whether anybody was told.
 */
export default function LegalVersions({
    versions,
    filters,
    documents,
    statuses,
    stats,
}: PageProps) {
    const [document, setDocument] = useState(filters.document);
    const [status, setStatus] = useState(filters.status);
    const [editing, setEditing] = useState<VersionRow | null>(null);

    const apply = (event: FormEvent): void => {
        event.preventDefault();
        router.get(
            urls.admin.legal.versions.index,
            {
                document: document || null,
                status: status || null,
            },
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Document versions" />

            <div className="grid gap-6">
                <PageHeader
                    title="Document versions"
                    description="Every version ever written, the dates that date it, and what replaced it."
                />

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard title="Versions" value={stats.versions} />
                    <StatCard title="Published" value={stats.published} />
                    <StatCard title="Drafts" value={stats.drafts} />
                    <StatCard title="Archived" value={stats.archived} />
                </div>

                <Card>
                    <CardContent className="grid gap-4">
                        <form
                            className="flex flex-wrap items-end gap-2"
                            onSubmit={apply}
                        >
                            <Select
                                value={document || 'all'}
                                onValueChange={(value) =>
                                    setDocument(value === 'all' ? '' : value)
                                }
                            >
                                <SelectTrigger className="w-64">
                                    <SelectValue placeholder="All documents" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All documents
                                    </SelectItem>
                                    {documents.map((option) => (
                                        <SelectItem
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>

                            <Select
                                value={status || 'all'}
                                onValueChange={(value) =>
                                    setStatus(value === 'all' ? '' : value)
                                }
                            >
                                <SelectTrigger className="w-44">
                                    <SelectValue placeholder="Any status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        Any status
                                    </SelectItem>
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

                            <Button type="submit" variant="outline">
                                Filter
                            </Button>
                        </form>

                        {editing !== null && (
                            <VersionEditor
                                version={editing}
                                onDone={() => setEditing(null)}
                            />
                        )}

                        <VersionRows
                            versions={versions}
                            onEdit={(version) =>
                                setEditing((current) =>
                                    current?.id === version.id ? null : version,
                                )
                            }
                        />

                        <Pagination paginator={versions} noun="versions" />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

LegalVersions.layout = {
    breadcrumbs: [
        {
            title: 'Document versions',
            href: urls.admin.legal.versions.index,
        },
    ],
};

/**
 * The table of versions with the action each one allows.
 */
function VersionRows({
    versions,
    onEdit,
}: {
    versions: Paginated<VersionRow>;
    onEdit: (version: VersionRow) => void;
}) {
    return (
        <>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Document</TableHead>
                        <TableHead>Version</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>Effective</TableHead>
                        <TableHead>Accepted</TableHead>
                        <TableHead>Announced</TableHead>
                        <TableHead className="text-right">Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {versions.data.map((version) => (
                        <VersionRowItem
                            key={version.id}
                            version={version}
                            onEdit={onEdit}
                        />
                    ))}
                </TableBody>
            </Table>

            {versions.data.length === 0 && (
                <p className="p-8 text-center text-sm text-muted-foreground">
                    No version matches these filters.
                </p>
            )}
        </>
    );
}

/**
 * One version, with the publication, announcement and editing actions it allows.
 */
function VersionRowItem({
    version,
    onEdit,
}: {
    version: VersionRow;
    onEdit: (version: VersionRow) => void;
}) {
    const confirm = useConfirm();

    const remove = async (): Promise<void> => {
        if (
            !(await confirm({
                title: `Delete version ${version.version}?`,
                description:
                    'Nobody has accepted or been told about this version, so it can go. This cannot be undone.',
                confirmLabel: 'Delete version',
                tone: 'danger',
            }))
        ) {
            return;
        }

        router.delete(urls.admin.legal.versions.destroy(version.id), {
            preserveScroll: true,
        });
    };

    return (
        <TableRow>
            <TableCell className="font-medium">
                {version.document}
                {version.document_slug && (
                    <p className="text-xs text-muted-foreground">
                        /legal/{version.document_slug}
                    </p>
                )}
            </TableCell>
            <TableCell>
                v{version.version}
                {version.change_summary && (
                    <p className="max-w-xs text-xs text-muted-foreground">
                        {version.change_summary}
                    </p>
                )}
            </TableCell>
            <TableCell>
                <Badge variant={badgeTone(version.status_tone)}>
                    {version.status_label}
                </Badge>
            </TableCell>
            <TableCell className="text-xs whitespace-nowrap text-muted-foreground">
                {version.effective_on ?? '—'}
            </TableCell>
            <TableCell>{version.acceptances_count}</TableCell>
            <TableCell className="text-xs text-muted-foreground">
                {version.notified_at
                    ? new Date(version.notified_at).toLocaleDateString()
                    : 'Not announced'}
            </TableCell>
            <TableCell>
                <div className="flex flex-wrap justify-end gap-1">
                    {version.status !== 'published' && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() =>
                                router.post(
                                    urls.admin.legal.versions.publish(
                                        version.id,
                                    ),
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            Publish
                        </Button>
                    )}
                    {version.status === 'published' && (
                        <>
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() =>
                                    router.post(
                                        urls.admin.legal.versions.notify(
                                            version.id,
                                        ),
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <Send />
                                Announce
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() =>
                                    router.post(
                                        urls.admin.legal.versions.archive(
                                            version.id,
                                        ),
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                Archive
                            </Button>
                        </>
                    )}
                    {version.can_edit && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => onEdit(version)}
                        >
                            <Pencil />
                            Edit
                        </Button>
                    )}
                    {version.can_delete && (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="text-destructive"
                            onClick={remove}
                        >
                            <Trash2 />
                        </Button>
                    )}
                </div>
            </TableCell>
        </TableRow>
    );
}

/**
 * Correct one version's wording.
 *
 * Only offered while nobody has accepted or been told about the version; the
 * server refuses the write otherwise and says to write a new version instead.
 */
function VersionEditor({
    version,
    onDone,
}: {
    version: VersionRow;
    onDone: () => void;
}) {
    const form = useForm({
        version: version.version,
        body: version.body ?? '',
        change_summary: version.change_summary ?? '',
        effective_on: version.effective_on ?? '',
        requires_reacceptance: version.requires_reacceptance,
        publish: false,
    });

    const submit = (event: FormEvent): void => {
        event.preventDefault();
        form.put(urls.admin.legal.versions.update(version.id), {
            preserveScroll: true,
            onSuccess: () => onDone(),
        });
    };

    return (
        <form
            className="grid gap-3 rounded-lg border border-border/70 bg-muted/30 p-4"
            onSubmit={submit}
        >
            <div className="flex flex-wrap items-baseline justify-between gap-2">
                <p className="text-sm font-medium">
                    Editing {version.document} v{version.version}
                </p>
                {version.status === 'published' && (
                    <p className="text-xs text-muted-foreground">
                        This version is live: saving republishes the page
                        immediately.
                    </p>
                )}
            </div>

            <div className="grid gap-3 sm:grid-cols-3">
                <div className="grid gap-1.5">
                    <Label htmlFor={`edit-version-${version.id}`}>
                        Version
                    </Label>
                    <Input
                        id={`edit-version-${version.id}`}
                        value={form.data.version}
                        onChange={(event) =>
                            form.setData('version', event.target.value)
                        }
                        required
                    />
                    {form.errors.version && (
                        <p className="text-xs text-destructive">
                            {form.errors.version}
                        </p>
                    )}
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor={`edit-effective-${version.id}`}>
                        Effective from
                    </Label>
                    <Input
                        id={`edit-effective-${version.id}`}
                        type="date"
                        value={form.data.effective_on}
                        onChange={(event) =>
                            form.setData('effective_on', event.target.value)
                        }
                    />
                    {form.errors.effective_on && (
                        <p className="text-xs text-destructive">
                            {form.errors.effective_on}
                        </p>
                    )}
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor={`edit-summary-${version.id}`}>
                        What changed
                    </Label>
                    <Input
                        id={`edit-summary-${version.id}`}
                        value={form.data.change_summary}
                        onChange={(event) =>
                            form.setData('change_summary', event.target.value)
                        }
                    />
                </div>
            </div>

            <div className="grid gap-1.5">
                <Label htmlFor={`edit-body-${version.id}`}>Policy text</Label>
                <textarea
                    id={`edit-body-${version.id}`}
                    value={form.data.body}
                    onChange={(event) =>
                        form.setData('body', event.target.value)
                    }
                    required
                    className="min-h-64 w-full rounded-md border border-input bg-background p-3 font-mono text-xs"
                />
                {form.errors.body && (
                    <p className="text-xs text-destructive">
                        {form.errors.body}
                    </p>
                )}
            </div>

            <div className="flex flex-wrap items-center gap-2">
                <Button type="submit" disabled={form.processing}>
                    Save changes
                </Button>
                <Button type="button" variant="ghost" onClick={onDone}>
                    Cancel
                </Button>
            </div>
        </form>
    );
}
