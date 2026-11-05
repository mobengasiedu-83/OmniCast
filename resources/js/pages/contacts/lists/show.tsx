import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    Download,
    FileUp,
    ListChecks,
    MailWarning,
    Search,
    ShieldBan,
    ShieldCheck,
    Trash2,
    Upload,
    UserPlus,
    Users,
} from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { useConfirm } from '@/components/confirm-dialog';
import EmptyState from '@/components/empty-state';
import InputError from '@/components/input-error';
import PageHeader from '@/components/page-header';
import Pagination from '@/components/pagination';
import StatCard from '@/components/stat-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
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
    ContactListCandidate,
    ContactListDetail,
    ContactListMember,
    ImportResult,
    Paginated,
} from '@/types';

type PageProps = {
    list: ContactListDetail;
    contacts: Paginated<ContactListMember>;
    filters: {
        search: string;
        status: string;
    };
    addSearch: string;
    candidates: ContactListCandidate[];
    candidatesTotal: number;
    stats: {
        contacts: number;
        reachable: number;
        dnd: number;
        missing_email: number;
    };
    can: {
        create: boolean;
        update: boolean;
        import: boolean;
        delete: boolean;
    };
    flash: {
        import: ImportResult | null;
    };
};

/** Timestamps are shown as a date, matching the contact book. */
const formatDate = (value: string | null): string =>
    value ? new Date(value).toLocaleDateString() : '—';

export default function ContactListShow({
    list,
    contacts,
    filters,
    addSearch,
    candidates,
    candidatesTotal,
    stats,
    can,
    flash,
}: PageProps) {
    const details = useForm({
        name: list.name,
        description: list.description ?? '',
    });
    const upload = useForm<{ file: File | null }>({ file: null });
    const [search, setSearch] = useState(filters.search);
    const [candidateSearch, setCandidateSearch] = useState(addSearch);
    const [selected, setSelected] = useState<number[]>([]);
    const confirm = useConfirm();

    /** Keep the member filters and the add-contact search in sync in the URL. */
    const visit = (overrides: Record<string, string | null>): void => {
        router.get(
            urls.contacts.lists.show(list.id),
            {
                search: filters.search || null,
                status: filters.status || null,
                add_search: addSearch || null,
                ...overrides,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const submitSearch = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        visit({ search: search || null });
    };

    const submitCandidateSearch = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        visit({ add_search: candidateSearch || null });
    };

    const saveDetails = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        details.put(urls.contacts.lists.update(list.id), {
            preserveScroll: true,
        });
    };

    const submitUpload = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        upload.post(urls.contacts.lists.import(list.id), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => upload.reset(),
        });
    };

    const addContacts = (): void => {
        if (selected.length === 0) {
            return;
        }

        router.post(
            urls.contacts.lists.attachContacts(list.id),
            { contact_ids: selected },
            {
                preserveScroll: true,
                onSuccess: () => setSelected([]),
            },
        );
    };

    const removeContact = async (member: ContactListMember): Promise<void> => {
        const confirmed = await confirm({
            title: `Remove ${member.name}?`,
            description: `The contact stays in the book, just not in ${list.name}.`,
            confirmLabel: 'Remove from list',
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        router.delete(urls.contacts.lists.detachContacts(list.id), {
            data: { contact_ids: [member.id] },
            preserveScroll: true,
        });
    };

    const archive = async (): Promise<void> => {
        const confirmed = await confirm({
            title: `Archive ${list.name}?`,
            description: 'Contacts stay in the book and in their other lists.',
            confirmLabel: 'Archive list',
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        router.delete(urls.contacts.lists.destroy(list.id));
    };

    const toggleCandidate = (id: number, checked: boolean): void => {
        setSelected((current) =>
            checked
                ? [...current, id]
                : current.filter((value) => value !== id),
        );
    };

    return (
        <>
            <Head title={list.name} />

            <div className="flex flex-col gap-4">
                <PageHeader
                    icon={ListChecks}
                    title={list.name}
                    badge={
                        <Badge variant="secondary">
                            {stats.contacts}{' '}
                            {stats.contacts === 1 ? 'contact' : 'contacts'}
                        </Badge>
                    }
                    description={
                        list.description ??
                        'Contacts in this list can be used as a campaign audience.'
                    }
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <Link href={urls.contacts.lists.index}>
                                    <ArrowLeft />
                                    All lists
                                </Link>
                            </Button>

                            {can.delete && (
                                <Button
                                    variant="ghost"
                                    onClick={() => void archive()}
                                >
                                    <Trash2 className="text-destructive" />
                                    Delete list
                                </Button>
                            )}
                        </>
                    }
                />

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        title="In this list"
                        value={stats.contacts}
                        description="Contacts a campaign would reach"
                        icon={Users}
                        tone="brand"
                    />
                    <StatCard
                        title="Reachable"
                        value={stats.reachable}
                        description="Not on the DND list"
                        icon={ShieldCheck}
                        tone="success"
                    />
                    <StatCard
                        title="On DND"
                        value={stats.dnd}
                        description="Skipped when the list is sent to"
                        icon={ShieldBan}
                        tone={stats.dnd > 0 ? 'warning' : 'neutral'}
                    />
                    <StatCard
                        title="Missing email"
                        value={stats.missing_email}
                        description="SMS and voice only"
                        icon={MailWarning}
                        tone={stats.missing_email > 0 ? 'warning' : 'neutral'}
                    />
                </div>

                {flash?.import && (
                    <Card className="gap-0 border-success/30 bg-success-soft py-3">
                        <CardContent className="flex flex-wrap items-center gap-x-4 gap-y-1 px-4 text-sm text-success">
                            <span className="flex items-center gap-2 font-medium">
                                <Upload className="size-4" />
                                Upload complete
                            </span>
                            <span>{flash.import.created} created</span>
                            <span>{flash.import.updated} updated</span>
                            <span>{flash.import.skipped} skipped</span>
                        </CardContent>
                    </Card>
                )}

                <div className="grid gap-4 lg:grid-cols-3">
                    <div className="flex flex-col gap-4 lg:col-span-2">
                        {/* MEMBERS_CARD */}
                    </div>

                    <div className="flex flex-col gap-4">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-sm font-medium">
                                    <ListChecks className="size-4 text-brand" />
                                    List details
                                </CardTitle>
                                <CardDescription>
                                    Rename the list or describe who belongs in
                                    it.
                                </CardDescription>
                            </CardHeader>

                            <CardContent>
                                <form
                                    onSubmit={saveDetails}
                                    className="flex flex-col gap-3"
                                >
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="details-name">
                                            Name
                                        </Label>
                                        <Input
                                            id="details-name"
                                            value={details.data.name}
                                            onChange={(event) =>
                                                details.setData(
                                                    'name',
                                                    event.target.value,
                                                )
                                            }
                                            disabled={!can.update}
                                            required
                                        />
                                        <InputError
                                            message={details.errors.name}
                                        />
                                    </div>

                                    <div className="grid gap-1.5">
                                        <Label htmlFor="details-description">
                                            Description
                                        </Label>
                                        <Input
                                            id="details-description"
                                            value={details.data.description}
                                            onChange={(event) =>
                                                details.setData(
                                                    'description',
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="Who is in this list"
                                            disabled={!can.update}
                                        />
                                        <InputError
                                            message={details.errors.description}
                                        />
                                    </div>

                                    {can.update && (
                                        <Button
                                            type="submit"
                                            disabled={details.processing}
                                        >
                                            {details.processing
                                                ? 'Saving…'
                                                : 'Save changes'}
                                        </Button>
                                    )}

                                    <p className="text-xs text-muted-foreground">
                                        Updated {formatDate(list.updated_at)} ·{' '}
                                        {list.campaigns_count}{' '}
                                        {list.campaigns_count === 1
                                            ? 'campaign'
                                            : 'campaigns'}{' '}
                                        use this list
                                    </p>
                                </form>
                            </CardContent>
                        </Card>
                        <Card id="contacts-upload">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-sm font-medium">
                                    <FileUp className="size-4 text-brand" />
                                    Upload contacts
                                </CardTitle>
                                <CardDescription>
                                    Upload a CSV export and every valid MSISDN
                                    lands in this list.
                                </CardDescription>
                            </CardHeader>

                            <CardContent>
                                {can.import ? (
                                    <form
                                        onSubmit={submitUpload}
                                        className="flex flex-col gap-3"
                                    >
                                        <div className="grid gap-1.5">
                                            <Label htmlFor="upload-file">
                                                CSV file
                                            </Label>
                                            <Input
                                                id="upload-file"
                                                type="file"
                                                accept=".csv,text/csv"
                                                onChange={(event) =>
                                                    upload.setData(
                                                        'file',
                                                        event.target
                                                            .files?.[0] ?? null,
                                                    )
                                                }
                                            />
                                            <InputError
                                                message={upload.errors.file}
                                            />
                                        </div>

                                        <Button
                                            type="submit"
                                            disabled={
                                                upload.processing ||
                                                upload.data.file === null
                                            }
                                        >
                                            <Upload />
                                            {upload.processing
                                                ? 'Uploading…'
                                                : 'Upload into this list'}
                                        </Button>

                                        <div className="flex flex-wrap items-center gap-3 text-xs">
                                            <a
                                                className="text-brand hover:underline"
                                                href={
                                                    urls.contacts.importTemplate
                                                }
                                            >
                                                <Download className="mr-1 inline size-3" />
                                                CSV template
                                            </a>
                                            <Link
                                                className="text-muted-foreground hover:underline"
                                                href={urls.contacts.import}
                                            >
                                                Full importer
                                            </Link>
                                        </div>
                                    </form>
                                ) : (
                                    <p className="text-xs text-muted-foreground">
                                        Your role cannot import contacts.
                                    </p>
                                )}
                            </CardContent>
                        </Card>
                        {/* ADD_CARD */}
                    </div>
                </div>
            </div>
        </>
    );
}

ContactListShow.layout = {
    breadcrumbs: [
        {
            title: 'Contacts',
            href: urls.contacts.index,
        },
        {
            title: 'Contact lists',
            href: urls.contacts.lists.index,
        },
    ],
};
