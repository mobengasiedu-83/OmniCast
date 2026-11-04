import { Head, Link, router } from '@inertiajs/react';
import { Ban, MailWarning, Plus, Search, ShieldCheck, SlidersHorizontal, Upload, Users } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import ClientFilter from '@/components/client-filter';
import EmptyState from '@/components/empty-state';
import PageHeader from '@/components/page-header';
import Pagination from '@/components/pagination';
import StatCard from '@/components/stat-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
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
import type {
    ClientOption,
    ContactListOption,
    ContactRow,
    ImportResult,
    Paginated,
    SelectOption,
    TagOption,
} from '@/types';

type PageProps = {
    contacts: Paginated<ContactRow>;
    filters: {
        search: string;
        contact_list_id: number | null;
        tag_id: number | null;
        quality: string;
    };
    contactLists: ContactListOption[];
    qualityFilters: SelectOption[];
    tags: TagOption[];
    stats: {
        total: number;
        broadcastable: number;
        dnd: number;
        missing_email: number;
    };
    can: {
        create: boolean;
        update: boolean;
        import: boolean;
    };
    clients: ClientOption[];
    tenantClientId: number | null;
    flash: {
        import: ImportResult | null;
    };
};

type BulkAction =
    | 'add_to_list'
    | 'remove_from_list'
    | 'add_tag'
    | 'mark_dnd'
    | 'clear_dnd'
    | 'delete';

export default function ContactsIndex({
    contacts,
    filters,
    contactLists,
    qualityFilters,
    tags,
    stats,
    can,
    clients,
    tenantClientId,
    flash,
}: PageProps) {
    const [search, setSearch] = useState(filters.search);
    const [selected, setSelected] = useState<number[]>([]);
    const [bulkAction, setBulkAction] = useState<BulkAction>('add_to_list');
    const [bulkValue, setBulkValue] = useState('');
    const [processing, setProcessing] = useState(false);

    const apply = (overrides: Record<string, string | number | null>): void => {
        setSelected([]);
        router.get(
            urls.contacts.index,
            {
                search: filters.search || null,
                contact_list_id: filters.contact_list_id,
                tag_id: filters.tag_id,
                quality: filters.quality || null,
                ...overrides,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        apply({ search: search || null });
    };

    const formatDate = (value: string | null): string =>
        value ? new Date(value).toLocaleDateString() : '—';

    const pageIds = contacts.data.map((contact) => contact.id);
    const allSelected =
        pageIds.length > 0 && pageIds.every((id) => selected.includes(id));
    const bulkNeedsList =
        bulkAction === 'add_to_list' || bulkAction === 'remove_from_list';
    const bulkNeedsTag = bulkAction === 'add_tag';
    const bulkBlocked =
        (bulkNeedsList && bulkValue === '') || (bulkNeedsTag && bulkValue === '');

    const toggleAll = (checked: boolean): void => {
        setSelected(checked ? pageIds : []);
    };

    const toggleOne = (id: number, checked: boolean): void => {
        setSelected((current) =>
            checked ? [...current, id] : current.filter((value) => value !== id),
        );
    };

    /** Every bulk operation hits one endpoint so the audit trail stays uniform. */
    const runBulk = (): void => {
        if (selected.length === 0 || bulkBlocked) {
            return;
        }

        setProcessing(true);
        router.post(
            urls.contacts.bulk,
            {
                action: bulkAction,
                contact_ids: selected,
                contact_list_id: bulkNeedsList ? Number(bulkValue) : null,
                tag_id: bulkNeedsTag ? Number(bulkValue) : null,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSelected([]);
                    setBulkValue('');
                },
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <>
            <Head title="Contacts" />

            <div className="flex flex-col gap-4">
                <PageHeader
                    icon={Users}
                    title="Contacts"
                    description="Manage the people campaigns reach and the numbers on the DND list."
                    actions={
                        <>
                            {can.import && (
                                <Button variant="outline" asChild>
                                    <Link href={urls.contacts.import}>
                                        <Upload />
                                        Import
                                    </Link>
                                </Button>
                            )}
                            <Button variant="outline" asChild><Link href={urls.contacts.audiences}><SlidersHorizontal /> Audiences & DND</Link></Button>

                            {can.create && (
                                <Button asChild>
                                    <Link href={urls.contacts.create}>
                                        <Plus />
                                        New contact
                                    </Link>
                                </Button>
                            )}
                        </>
                    }
                />

                                {flash?.import && (
                    <Card className="border-success/30 bg-success-soft gap-0 py-3">
                        <CardContent className="text-success flex flex-wrap items-center gap-x-4 gap-y-1 px-4 text-sm">
                            <span className="flex items-center gap-2 font-medium">
                                <ShieldCheck className="size-4" />
                                Import complete
                            </span>
                            <span>{flash.import.created} created</span>
                            <span>{flash.import.updated} updated</span>
                            <span>{flash.import.skipped} skipped</span>
                        </CardContent>
                    </Card>
                )}

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        title="Total contacts"
                        value={stats.total}
                        description="Everyone in the book"
                        icon={Users}
                        tone="brand"
                        className="animate-fade-in-up"
                    />
                    <StatCard
                        title="Broadcastable"
                        value={stats.broadcastable}
                        description="Reachable and not on DND"
                        icon={ShieldCheck}
                        tone="success"
                        className="animate-fade-in-up"
                        style={{ animationDelay: '60ms' }}
                    />
                    <StatCard
                        title="Missing email"
                        value={stats.missing_email}
                        description="SMS or voice only"
                        icon={MailWarning}
                        tone="warning"
                        className="animate-fade-in-up"
                        style={{ animationDelay: '120ms' }}
                    />
                    <StatCard
                        title="DND"
                        value={stats.dnd}
                        description="Blocked from every campaign"
                        icon={Ban}
                        tone="danger"
                        className="animate-fade-in-up"
                        style={{ animationDelay: '180ms' }}
                    />
                </div>

                <Card className="py-3">
                    <CardContent className="flex flex-wrap items-end gap-2 px-4">
                        <form
                            onSubmit={submit}
                            className="flex flex-1 items-center gap-2"
                        >
                            <div className="relative w-full max-w-xs">
                                <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2" />
                                <Input
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    placeholder="Search name, phone or email"
                                    className="pl-8"
                                />
                            </div>

                            <Button type="submit" variant="secondary">
                                Search
                            </Button>
                        </form>

                        <Select
                            value={
                                filters.contact_list_id
                                    ? String(filters.contact_list_id)
                                    : 'all'
                            }
                            onValueChange={(value) =>
                                apply({
                                    contact_list_id:
                                        value === 'all' ? null : value,
                                })
                            }
                        >
                            <SelectTrigger className="w-44">
                                <SelectValue placeholder="All lists" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All lists</SelectItem>
                                {contactLists.map((list) => (
                                    <SelectItem
                                        key={list.id}
                                        value={String(list.id)}
                                    >
                                        {list.name} ({list.contacts_count})
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <Select value={filters.tag_id ? String(filters.tag_id) : 'all'} onValueChange={(value) => apply({ tag_id: value === 'all' ? null : value })}>
                            <SelectTrigger className="w-40"><SelectValue placeholder="Any tag" /></SelectTrigger>
                            <SelectContent><SelectItem value="all">Any tag</SelectItem>{tags.map((tag) => <SelectItem key={tag.id} value={String(tag.id)}>{tag.name}</SelectItem>)}</SelectContent>
                        </Select>

                        <Select
                            value={filters.quality || 'all'}
                            onValueChange={(value) =>
                                apply({ quality: value === 'all' ? null : value })
                            }
                        >
                            <SelectTrigger className="w-44">
                                <SelectValue placeholder="Any quality" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Any quality</SelectItem>
                                {qualityFilters.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <ClientFilter
                            clients={clients}
                            value={tenantClientId}
                            query={{
                                search: filters.search || null,
                                contact_list_id: filters.contact_list_id,
                                tag_id: filters.tag_id,
                                quality: filters.quality || null,
                            }}
                        />
                    </CardContent>
                </Card>

                {can.update && selected.length > 0 && (
                    <Card className="border-brand/30 bg-brand-soft/60 py-3">
                        <CardContent className="flex flex-wrap items-center gap-2 px-4">
                            <span className="text-sm font-medium">
                                {selected.length} selected
                            </span>

                            <Select
                                value={bulkAction}
                                onValueChange={(value) => {
                                    setBulkAction(value as BulkAction);
                                    setBulkValue('');
                                }}
                            >
                                <SelectTrigger className="w-52">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="add_to_list">
                                        Add to list
                                    </SelectItem>
                                    <SelectItem value="remove_from_list">
                                        Remove from list
                                    </SelectItem>
                                    <SelectItem value="add_tag">
                                        Add tag
                                    </SelectItem>
                                    <SelectItem value="mark_dnd">
                                        Add to DND list
                                    </SelectItem>
                                    <SelectItem value="clear_dnd">
                                        Remove from DND list
                                    </SelectItem>
                                    <SelectItem value="delete">
                                        Delete contacts
                                    </SelectItem>
                                </SelectContent>
                            </Select>

                            {bulkNeedsList && (
                                <Select
                                    value={bulkValue}
                                    onValueChange={setBulkValue}
                                >
                                    <SelectTrigger className="w-44">
                                        <SelectValue placeholder="Choose list" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {contactLists.map((list) => (
                                            <SelectItem
                                                key={list.id}
                                                value={String(list.id)}
                                            >
                                                {list.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            )}

                            {bulkAction === 'add_tag' && (
                                <Select
                                    value={bulkValue}
                                    onValueChange={setBulkValue}
                                >
                                    <SelectTrigger className="w-44">
                                        <SelectValue placeholder="Choose tag" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {tags.map((tag) => (
                                            <SelectItem
                                                key={tag.id}
                                                value={String(tag.id)}
                                            >
                                                {tag.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            )}

                            <Button
                                onClick={runBulk}
                                disabled={processing || bulkBlocked}
                            >
                                {processing ? 'Applying…' : 'Apply'}
                            </Button>

                            <Button
                                variant="ghost"
                                onClick={() => setSelected([])}
                            >
                                Clear
                            </Button>
                        </CardContent>
                    </Card>
                )}

                <Card className="gap-0 overflow-hidden py-0">
                    <CardHeader className="flex flex-row items-center justify-between gap-2 border-b border-border/60 py-3">
                        <CardTitle className="text-sm font-medium">
                            {contacts.total}{' '}
                            {contacts.total === 1 ? 'contact' : 'contacts'}
                        </CardTitle>
                        <p className="text-muted-foreground text-xs">
                            Page {contacts.current_page} of {contacts.last_page}
                        </p>
                    </CardHeader>

                    <CardContent className="px-0">{contacts.data.length === 0 ? (
                            <EmptyState
                                icon={Users}
                                title="No contacts match these filters"
                                description="Adjust the search, list or quality filters to see contacts again."
                            />
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        {can.update && (
                                            <TableHead className="w-10">
                                                <Checkbox
                                                    checked={allSelected}
                                                    onCheckedChange={(value) =>
                                                        toggleAll(value === true)
                                                    }
                                                    aria-label="Select all contacts on this page"
                                                />
                                            </TableHead>
                                        )}
                                        <TableHead>Contact</TableHead>
                                        <TableHead>Lists</TableHead>
                                        <TableHead>DND</TableHead>
                                        <TableHead>Added</TableHead>
                                        <TableHead className="text-right">
                                            Actions
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>

                                <TableBody>{contacts.data.map((contact) => (
                                        <TableRow key={contact.id}>
                                            {can.update && (
                                                <TableCell>
                                                    <Checkbox
                                                        checked={selected.includes(
                                                            contact.id,
                                                        )}
                                                        onCheckedChange={(
                                                            value,
                                                        ) =>
                                                            toggleOne(
                                                                contact.id,
                                                                value === true,
                                                            )
                                                        }
                                                        aria-label={`Select ${contact.name}`}
                                                    />
                                                </TableCell>
                                            )}
                                            <TableCell>
                                                <div className="flex flex-col">
                                                    <Link
                                                        href={urls.contacts.show(
                                                            contact.id,
                                                        )}
                                                        className="font-medium hover:underline"
                                                    >
                                                        {contact.name}
                                                    </Link>
                                                    <span className="text-muted-foreground text-xs">
                                                        {contact.phone}
                                                        {contact.email
                                                            ? ` · ${contact.email}`
                                                            : ''}
                                                    </span>
                                                </div>
                                            </TableCell>

                                            <TableCell className="text-muted-foreground text-xs">
                                                {contact.lists.length > 0
                                                    ? contact.lists.join(', ')
                                                    : '—'}
                                            </TableCell>

                                            <TableCell>
                                                {contact.dnd ? (
                                                    <div className="flex flex-col gap-1">
                                                        <Badge variant="destructive">
                                                            DND
                                                        </Badge>
                                                        <span className="text-muted-foreground text-xs">
                                                            Blocked on every
                                                            channel
                                                        </span>
                                                    </div>
                                                ) : (
                                                    <Badge variant="success">
                                                        Reachable
                                                    </Badge>
                                                )}
                                            </TableCell>

                                            <TableCell className="text-muted-foreground text-xs">
                                                {formatDate(
                                                    contact.created_at,
                                                )}
                                            </TableCell>

                                            <TableCell className="text-right">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <Link
                                                        href={urls.contacts.show(
                                                            contact.id,
                                                        )}
                                                    >
                                                        View
                                                    </Link>
                                                </Button>

                                                {can.update && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={urls.contacts.edit(
                                                                contact.id,
                                                            )}
                                                        >
                                                            Edit
                                                        </Link>
                                                    </Button>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}</TableBody>
                            </Table>
                        )}</CardContent>
                </Card>

                <Pagination paginator={contacts} noun="contacts" />
            </div>
        </>
    );
}

ContactsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Contacts',
            href: urls.contacts.index,
        },
    ],
};
