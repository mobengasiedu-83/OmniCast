import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    BellRing,
    FilePlus2,
    Pencil,
    Plus,
    Printer,
    Send,
    Trash2,
} from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { useConfirm } from '@/components/confirm-dialog';
import PageHeader from '@/components/page-header';
import StatCard from '@/components/stat-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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

type VersionRow = {
    id: number;
    version: string;
    status: string;
    status_label: string;
    status_tone: string;
    effective_on: string | null;
    published_at: string | null;
    notified_at: string | null;
    requires_reacceptance: boolean;
    change_summary: string | null;
    acceptances_count: number;
    notifications_count: number;
    can_edit: boolean;
    can_delete: boolean;
    body: string | null;
};

type LegalDocumentRow = {
    id: number;
    slug: string;
    title: string;
    summary: string | null;
    sort_order: number;
    is_active: boolean;
    url: string;
    print_url: string;
    can_delete: boolean;
    requires: { registration: boolean; campaign: boolean; top_up: boolean };
    acceptances_count: number;
    published: {
        id: number;
        version: string;
        effective_on: string | null;
        published_at: string | null;
        notified_at: string | null;
        requires_reacceptance: boolean;
    } | null;
    versions: VersionRow[];
};

type PageProps = {
    documents: LegalDocumentRow[];
    stats: {
        documents: number;
        published: number;
        drafts: number;
        acceptances: number;
        notified: number;
    };
};

/**
 * Legal document management.
 *
 * Writing and publishing are separate actions on purpose: a draft can sit beside
 * the live version for as long as review takes, and announcing a version is a
 * third, deliberate click. Nothing here edits a version a customer has already
 * accepted — a change is a new version.
 */
export default function LegalDocuments({ documents, stats }: PageProps) {
    return (
        <>
            <Head title="Legal documents" />

            <div className="grid gap-6">
                <PageHeader
                    title="Legal documents"
                    description="Write, publish and announce the policies that govern the platform."
                    actions={
                        <Button variant="outline" asChild>
                            <Link href={urls.admin.legal.acceptances.index}>
                                Acceptance register
                            </Link>
                        </Button>
                    }
                />

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    <StatCard title="Documents" value={stats.documents} />
                    <StatCard
                        title="Published versions"
                        value={stats.published}
                    />
                    <StatCard title="Drafts" value={stats.drafts} />
                    <StatCard title="Acceptances" value={stats.acceptances} />
                    <StatCard title="Announcements" value={stats.notified} />
                </div>

                <NewDocumentForm />

                {documents.map((document) => (
                    <DocumentCard key={document.id} document={document} />
                ))}

                {documents.length === 0 && (
                    <Card>
                        <CardContent className="py-10 text-center text-sm text-muted-foreground">
                            No policy documents yet. Create one above, then
                            write its first version.
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

LegalDocuments.layout = {
    breadcrumbs: [
        {
            title: 'Legal documents',
            href: urls.admin.legal.documents.index,
        },
    ],
};

/**
 * Register a new policy. The first version is written on the card below.
 */
function NewDocumentForm() {
    const [open, setOpen] = useState(false);
    const form = useForm({
        slug: '',
        title: '',
        summary: '',
        sort_order: 0,
        is_active: true,
        requires_acceptance_at_registration: false,
        requires_acceptance_on_campaign_submission: false,
        requires_acceptance_on_wallet_top_up: false,
    });

    const submit = (event: FormEvent): void => {
        event.preventDefault();
        form.post(urls.admin.legal.documents.store, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    };

    if (!open) {
        return (
            <Button
                type="button"
                variant="outline"
                className="w-fit"
                onClick={() => setOpen(true)}
            >
                <FilePlus2 />
                New document
            </Button>
        );
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <FilePlus2 className="size-4 text-brand" />
                    New document
                </CardTitle>
            </CardHeader>
            <CardContent>
                <form className="grid gap-4" onSubmit={submit}>
                    <div className="grid gap-2 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="legal-title">Title</Label>
                            <Input
                                id="legal-title"
                                value={form.data.title}
                                onChange={(event) =>
                                    form.setData('title', event.target.value)
                                }
                                required
                            />
                            {form.errors.title && (
                                <p className="text-xs text-destructive">
                                    {form.errors.title}
                                </p>
                            )}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="legal-slug">URL handle</Label>
                            <Input
                                id="legal-slug"
                                value={form.data.slug}
                                onChange={(event) =>
                                    form.setData('slug', event.target.value)
                                }
                                placeholder="cookie-policy"
                                required
                            />
                            {form.errors.slug && (
                                <p className="text-xs text-destructive">
                                    {form.errors.slug}
                                </p>
                            )}
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="legal-summary">Summary</Label>
                        <Input
                            id="legal-summary"
                            value={form.data.summary}
                            onChange={(event) =>
                                form.setData('summary', event.target.value)
                            }
                            placeholder="One sentence shown beside every link to the document."
                        />
                    </div>

                    <AcceptanceCheckboxes
                        values={form.data}
                        onChange={(field, value) => form.setData(field, value)}
                    />

                    <div className="flex gap-2">
                        <Button type="submit" disabled={form.processing}>
                            Create document
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => setOpen(false)}
                        >
                            Cancel
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}

type AcceptanceValues = {
    requires_acceptance_at_registration: boolean;
    requires_acceptance_on_campaign_submission: boolean;
    requires_acceptance_on_wallet_top_up: boolean;
};

/**
 * Where acceptance of a document is collected.
 *
 * A document can require acknowledgement at more than one point: the acceptable
 * use policy is accepted with the application and again on every campaign
 * submission, which is what keeps the evidence tied to the activity.
 */
function AcceptanceCheckboxes({
    values,
    onChange,
}: {
    values: AcceptanceValues;
    onChange: (field: keyof AcceptanceValues, value: boolean) => void;
}) {
    return (
        <fieldset className="grid gap-2">
            <legend className="text-sm font-medium">
                Acceptance is collected at
            </legend>
            <label className="flex items-center gap-2 text-sm">
                <Checkbox
                    checked={values.requires_acceptance_at_registration}
                    onCheckedChange={(checked) =>
                        onChange(
                            'requires_acceptance_at_registration',
                            checked === true,
                        )
                    }
                />
                Account application
            </label>
            <label className="flex items-center gap-2 text-sm">
                <Checkbox
                    checked={values.requires_acceptance_on_campaign_submission}
                    onCheckedChange={(checked) =>
                        onChange(
                            'requires_acceptance_on_campaign_submission',
                            checked === true,
                        )
                    }
                />
                Campaign submission
            </label>
            <label className="flex items-center gap-2 text-sm">
                <Checkbox
                    checked={values.requires_acceptance_on_wallet_top_up}
                    onCheckedChange={(checked) =>
                        onChange(
                            'requires_acceptance_on_wallet_top_up',
                            checked === true,
                        )
                    }
                />
                Wallet top-up
            </label>
        </fieldset>
    );
}

/**
 * One document: what is live, what has been written, and the way to a new version.
 */
function DocumentCard({ document }: { document: LegalDocumentRow }) {
    const [composing, setComposing] = useState(false);
    const [editing, setEditing] = useState(false);
    const confirm = useConfirm();

    const remove = async (): Promise<void> => {
        if (
            !(await confirm({
                title: `Delete “${document.title}”?`,
                description:
                    'The document and its versions are removed. This cannot be undone.',
                confirmLabel: 'Delete document',
                tone: 'danger',
            }))
        ) {
            return;
        }

        router.delete(urls.admin.legal.documents.destroy(document.id), {
            preserveScroll: true,
        });
    };

    return (
        <Card>
            <CardHeader>
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <CardTitle className="flex flex-wrap items-center gap-2">
                            {document.title}
                            <Badge
                                variant={
                                    document.is_active ? 'success' : 'muted'
                                }
                            >
                                {document.is_active
                                    ? 'On the site'
                                    : 'Withdrawn'}
                            </Badge>
                            {document.published && (
                                <Badge variant="info">
                                    v{document.published.version} live
                                </Badge>
                            )}
                        </CardTitle>
                        <p className="mt-1 text-xs text-muted-foreground">
                            /legal/{document.slug} ·{' '}
                            {document.acceptances_count} acceptance
                            {document.acceptances_count === 1 ? '' : 's'}
                        </p>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" size="sm" asChild>
                            <a
                                href={document.url}
                                target="_blank"
                                rel="noreferrer"
                            >
                                Preview
                            </a>
                        </Button>
                        <Button variant="outline" size="sm" asChild>
                            <a
                                href={document.print_url}
                                target="_blank"
                                rel="noreferrer"
                                title="Print-ready copy to save as a PDF"
                            >
                                <Printer />
                                PDF
                            </a>
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => setEditing((open) => !open)}
                        >
                            <Pencil />
                            Edit
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() =>
                                router.patch(
                                    urls.admin.legal.documents.toggle(
                                        document.id,
                                    ),
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            {document.is_active ? 'Withdraw' : 'Restore'}
                        </Button>
                        {document.can_delete && (
                            <Button
                                variant="ghost"
                                size="sm"
                                className="text-destructive"
                                onClick={remove}
                            >
                                <Trash2 />
                                Delete
                            </Button>
                        )}
                        <Button
                            size="sm"
                            onClick={() => setComposing((open) => !open)}
                        >
                            <Plus />
                            New version
                        </Button>
                    </div>
                </div>

                <div className="mt-3 flex flex-wrap gap-1.5">
                    {document.requires.registration && (
                        <Badge variant="secondary">Application</Badge>
                    )}
                    {document.requires.campaign && (
                        <Badge variant="secondary">Campaign submit</Badge>
                    )}
                    {document.requires.top_up && (
                        <Badge variant="secondary">Wallet top-up</Badge>
                    )}
                </div>
            </CardHeader>

            <CardContent className="space-y-4">
                {editing && (
                    <DocumentEditor
                        document={document}
                        onDone={() => setEditing(false)}
                    />
                )}

                {composing && (
                    <VersionComposer
                        document={document}
                        onDone={() => setComposing(false)}
                    />
                )}

                <VersionTable document={document} />
            </CardContent>
        </Card>
    );
}

/**
 * The version history of one document, with the action each version allows.
 */
function VersionTable({ document }: { document: LegalDocumentRow }) {
    const [editing, setEditing] = useState<VersionRow | null>(null);
    const confirm = useConfirm();

    const remove = async (version: VersionRow): Promise<void> => {
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

    if (document.versions.length === 0) {
        return (
            <p className="py-4 text-center text-sm text-muted-foreground">
                Nothing has been written for this document yet. Use “New
                version” to paste the policy text.
            </p>
        );
    }

    return (
        <>
            {editing !== null && (
                <VersionComposer
                    document={document}
                    version={editing}
                    onDone={() => setEditing(null)}
                />
            )}

            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Version</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>Effective</TableHead>
                        <TableHead>Acceptances</TableHead>
                        <TableHead>Announced</TableHead>
                        <TableHead className="text-right">Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {document.versions.map((version) => (
                        <TableRow key={version.id}>
                            <TableCell className="font-medium">
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
                            <TableCell className="text-xs text-muted-foreground">
                                {version.effective_on ?? '—'}
                            </TableCell>
                            <TableCell>{version.acceptances_count}</TableCell>
                            <TableCell className="text-xs text-muted-foreground">
                                {version.notified_at
                                    ? new Date(
                                          version.notified_at,
                                      ).toLocaleDateString()
                                    : 'Not announced'}
                            </TableCell>
                            <TableCell>
                                <div className="flex justify-end gap-1">
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
                                                        {
                                                            preserveScroll: true,
                                                        },
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
                                                        {
                                                            preserveScroll: true,
                                                        },
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
                                            onClick={() =>
                                                setEditing((current) =>
                                                    current?.id === version.id
                                                        ? null
                                                        : version,
                                                )
                                            }
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
                                            onClick={() => remove(version)}
                                        >
                                            <Trash2 />
                                        </Button>
                                    )}
                                </div>
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </>
    );
}

/**
 * The next number to offer in the composer: the highest minor version plus one.
 */
function suggestVersion(versions: VersionRow[]): string {
    const parsed = versions
        .map((version) =>
            version.version.split('.').map((part) => Number.parseInt(part, 10)),
        )
        .filter((parts) => parts.length > 0 && parts.every(Number.isFinite));

    if (parsed.length === 0) {
        return '1.0';
    }

    const highest = parsed.sort((left, right) => {
        const major = (right[0] ?? 0) - (left[0] ?? 0);

        return major !== 0 ? major : (right[1] ?? 0) - (left[1] ?? 0);
    })[0];

    return `${highest[0] ?? 1}.${(highest[1] ?? 0) + 1}`;
}

/**
 * Write a new version of a policy.
 *
 * The body is markdown: headings with `##`, bullets with `-`, and blank lines
 * between paragraphs. Raw HTML is stripped when the page renders, so anything
 * that has to survive has to be written as markdown.
 */
function VersionComposer({
    document,
    version,
    onDone,
}: {
    document: LegalDocumentRow;
    version?: VersionRow;
    onDone: () => void;
}) {
    const key = `${document.id}-${version?.id ?? 'new'}`;

    const form = useForm({
        version: version?.version ?? suggestVersion(document.versions),
        body: version?.body ?? '',
        change_summary: version?.change_summary ?? '',
        effective_on: version?.effective_on ?? '',
        requires_reacceptance: version?.requires_reacceptance ?? true,
        publish: false,
    });

    const submit = (event: FormEvent): void => {
        event.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onDone();
            },
        };

        if (version !== undefined) {
            form.put(urls.admin.legal.versions.update(version.id), options);

            return;
        }

        form.post(
            urls.admin.legal.documents.storeVersion(document.id),
            options,
        );
    };

    return (
        <form
            className="grid gap-3 rounded-lg border border-border/70 bg-muted/30 p-4"
            onSubmit={submit}
        >
            <div className="grid gap-3 sm:grid-cols-3">
                <div className="grid gap-1.5">
                    <Label htmlFor={`version-${key}`}>Version</Label>
                    <Input
                        id={`version-${key}`}
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
                    <Label htmlFor={`effective-${key}`}>Effective from</Label>
                    <Input
                        id={`effective-${key}`}
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
                    <Label htmlFor={`summary-${key}`}>What changed</Label>
                    <Input
                        id={`summary-${key}`}
                        value={form.data.change_summary}
                        onChange={(event) =>
                            form.setData('change_summary', event.target.value)
                        }
                        placeholder="Shown beside the version and in the announcement."
                    />
                </div>
            </div>

            <div className="grid gap-1.5">
                <Label htmlFor={`body-${key}`}>Policy text</Label>
                <textarea
                    id={`body-${key}`}
                    value={form.data.body}
                    onChange={(event) =>
                        form.setData('body', event.target.value)
                    }
                    required
                    className="min-h-64 w-full rounded-md border border-input bg-background p-3 font-mono text-xs"
                    placeholder={
                        '# Document title\n\n## 1. A section\n\nA paragraph.'
                    }
                />
                {form.errors.body && (
                    <p className="text-xs text-destructive">
                        {form.errors.body}
                    </p>
                )}
            </div>

            <label className="flex items-center gap-2 text-sm">
                <Checkbox
                    checked={form.data.requires_reacceptance}
                    onCheckedChange={(checked) =>
                        form.setData('requires_reacceptance', checked === true)
                    }
                />
                Customers have to acknowledge this version
            </label>

            {version === undefined && (
                <label className="flex items-center gap-2 text-sm">
                    <Checkbox
                        checked={form.data.publish}
                        onCheckedChange={(checked) =>
                            form.setData('publish', checked === true)
                        }
                    />
                    Publish immediately (otherwise the version stays a draft)
                </label>
            )}

            <div className="flex flex-wrap items-center gap-2">
                <Button type="submit" disabled={form.processing}>
                    <BellRing />
                    {version === undefined ? 'Save version' : 'Save changes'}
                </Button>
                <Button type="button" variant="ghost" onClick={onDone}>
                    Cancel
                </Button>
                {version !== undefined && version.status === 'published' && (
                    <p className="text-xs text-muted-foreground">
                        This version is live: saving republishes the page
                        immediately. Nobody has accepted it yet, so the text can
                        still be corrected in place.
                    </p>
                )}
            </div>
        </form>
    );
}

/**
 * Correct a document's identity.
 *
 * The title customers see, the handle in the URL, the sentence printed beside
 * every link, its position in the list and where acceptance is collected. The
 * wording itself lives in the versions below, so editing here never changes the
 * text anybody agreed to.
 */
function DocumentEditor({
    document,
    onDone,
}: {
    document: LegalDocumentRow;
    onDone: () => void;
}) {
    const form = useForm({
        slug: document.slug,
        title: document.title,
        summary: document.summary ?? '',
        sort_order: String(document.sort_order),
        is_active: document.is_active,
        requires_acceptance_at_registration: document.requires.registration,
        requires_acceptance_on_campaign_submission: document.requires.campaign,
        requires_acceptance_on_wallet_top_up: document.requires.top_up,
    });

    const submit = (event: FormEvent): void => {
        event.preventDefault();
        form.put(urls.admin.legal.documents.update(document.id), {
            preserveScroll: true,
            onSuccess: () => onDone(),
        });
    };

    return (
        <form
            className="grid gap-3 rounded-lg border border-border/70 bg-muted/30 p-4"
            onSubmit={submit}
        >
            <div className="grid gap-3 sm:grid-cols-2">
                <div className="grid gap-1.5">
                    <Label htmlFor={`doc-title-${document.id}`}>Title</Label>
                    <Input
                        id={`doc-title-${document.id}`}
                        value={form.data.title}
                        onChange={(event) =>
                            form.setData('title', event.target.value)
                        }
                        required
                    />
                    {form.errors.title && (
                        <p className="text-xs text-destructive">
                            {form.errors.title}
                        </p>
                    )}
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor={`doc-slug-${document.id}`}>
                        URL handle
                    </Label>
                    <Input
                        id={`doc-slug-${document.id}`}
                        value={form.data.slug}
                        onChange={(event) =>
                            form.setData('slug', event.target.value)
                        }
                        required
                    />
                    {form.errors.slug && (
                        <p className="text-xs text-destructive">
                            {form.errors.slug}
                        </p>
                    )}
                    <p className="text-xs text-muted-foreground">
                        Changing the handle breaks links customers and
                        regulators have already saved.
                    </p>
                </div>
            </div>

            <div className="grid gap-1.5">
                <Label htmlFor={`doc-summary-${document.id}`}>Summary</Label>
                <Input
                    id={`doc-summary-${document.id}`}
                    value={form.data.summary}
                    onChange={(event) =>
                        form.setData('summary', event.target.value)
                    }
                />
                {form.errors.summary && (
                    <p className="text-xs text-destructive">
                        {form.errors.summary}
                    </p>
                )}
            </div>

            <div className="grid gap-3 sm:grid-cols-2">
                <div className="grid gap-1.5">
                    <Label htmlFor={`doc-order-${document.id}`}>
                        Position in the list
                    </Label>
                    <Input
                        id={`doc-order-${document.id}`}
                        type="number"
                        min={0}
                        max={100}
                        value={form.data.sort_order}
                        onChange={(event) =>
                            form.setData('sort_order', event.target.value)
                        }
                    />
                    {form.errors.sort_order && (
                        <p className="text-xs text-destructive">
                            {form.errors.sort_order}
                        </p>
                    )}
                </div>
                <label className="flex items-center gap-2 self-end text-sm">
                    <Checkbox
                        checked={form.data.is_active}
                        onCheckedChange={(checked) =>
                            form.setData('is_active', checked === true)
                        }
                    />
                    Show on the public site
                </label>
            </div>

            <AcceptanceCheckboxes
                values={form.data}
                onChange={(field, value) => form.setData(field, value)}
            />

            <div className="flex flex-wrap items-center gap-2">
                <Button type="submit" disabled={form.processing}>
                    Save document
                </Button>
                <Button type="button" variant="ghost" onClick={onDone}>
                    Cancel
                </Button>
            </div>
        </form>
    );
}
