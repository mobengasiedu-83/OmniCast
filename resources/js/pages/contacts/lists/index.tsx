import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    FolderOpen,
    ListChecks,
    Plus,
    Search,
    Trash2,
    Upload,
    UserRoundX,
    Users,
} from 'lucide-react';
import { useState, type FormEvent } from 'react';
import ClientFilter from '@/components/client-filter';
import EmptyState from '@/components/empty-state';
import InputError from '@/components/input-error';
import PageHeader from '@/components/page-header';
import StatCard from '@/components/stat-card';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import { urls } from '@/lib/urls';
import type { ClientOption, ContactListSummary } from '@/types';

type PageProps = {
    lists: ContactListSummary[];
    filters: { search: string };
    stats: {
        lists: number;
        assigned: number;
        unassigned: number;
        empty: number;
        largest: string | null;
        largest_count: number;
    };
    can: {
        create: boolean;
        update: boolean;
        import: boolean;
        delete: boolean;
    };
    clients: ClientOption[];
    tenantClientId: number | null;
};

/** Dates in the workspace are rendered without a time. */
const formatDate = (value: string | null): string =>
    value ? new Date(value).toLocaleDateString() : '—';

export default function ContactListsIndex({
    lists,
    filters,
    stats,
    can,
    clients,
    tenantClientId,
}: PageProps) {
    const form = useForm({ name: '', description: '' });
    const [search, setSearch] = useState(filters.search);

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        form.post(urls.contacts.lists.store, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    const submitSearch = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        router.get(
            urls.contacts.lists.index,
            {
                search: search || null,
                client_id: clients.length > 0 ? tenantClientId : null,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    /** Archiving keeps the contacts; only the grouping goes away. */
    const archive = (list: ContactListSummary): void => {
        if (
            !window.confirm(
                `Archive "${list.name}"? The contacts stay in the book and in their other lists.`,
            )
        ) {
            return;
        }

        router.delete(urls.contacts.lists.destroy(list.id), {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Contact lists" />

            <div className="flex flex-col gap-4">
                <PageHeader
                    icon={ListChecks}
                    title="Contact lists"
                    description="Group contacts into reusable audiences. Open a list to review its members, upload a CSV straight into it or rename it."
                    actions={
                        <>
                            {can.import && (
                                <Button variant="outline" asChild>
                                    <Link href={urls.contacts.import}>
                                        <Upload />
                                        Import CSV
                                    </Link>
                                </Button>
                            )}

                            <ClientFilter
                                clients={clients}
                                value={tenantClientId}
                                query={{ search: filters.search || null }}
                            />
                        </>
                    }
                />

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        title="Contact lists"
                        value={stats.lists}
                        description={
                            stats.largest
                                ? `Largest: ${stats.largest} (${stats.largest_count})`
                                : 'No lists yet'
                        }
                        icon={ListChecks}
                        tone="brand"
                    />
                    <StatCard
                        title="Contacts in lists"
                        value={stats.assigned}
                        description="Reachable through a saved list"
                        icon={Users}
                        tone="success"
                    />
                    <StatCard
                        title="Not in a list"
                        value={stats.unassigned}
                        description="Only reachable through a segment"
                        icon={UserRoundX}
                        tone={stats.unassigned > 0 ? 'warning' : 'neutral'}
                    />
                    <StatCard
                        title="Empty lists"
                        value={stats.empty}
                        description="Waiting for a first upload"
                        icon={FolderOpen}
                        tone={stats.empty > 0 ? 'warning' : 'success'}
                    />
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-sm font-medium">
                                <Plus className="text-brand size-4" />
                                New list
                            </CardTitle>
                            <CardDescription>
                                A list can hold any number of contacts and feed
                                any campaign.
                            </CardDescription>
                        </CardHeader>

                        <CardContent>
                            <form
                                onSubmit={submit}
                                className="flex flex-col gap-3"
                            >
                                <div className="grid gap-1.5">
                                    <Label htmlFor="list-name">
                                        List name
                                    </Label>
                                    <Input
                                        id="list-name"
                                        value={form.data.name}
                                        onChange={(event) =>
                                            form.setData(
                                                'name',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="October arrears"
                                        required
                                    />
                                    <InputError message={form.errors.name} />
                                </div>

                                <div className="grid gap-1.5">
                                    <Label htmlFor="list-description">
                                        Description (optional)
                                    </Label>
                                    <Input
                                        id="list-description"
                                        value={form.data.description}
                                        onChange={(event) =>
                                            form.setData(
                                                'description',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Customers with an outstanding balance"
                                    />
                                    <InputError
                                        message={form.errors.description}
                                    />
                                </div>

                                <Button
                                    type="submit"
                                    disabled={form.processing || !can.create}
                                >
                                    <Plus />
                                    {form.processing
                                        ? 'Creating…'
                                        : 'Create list'}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-sm font-medium">
                                <Search className="text-brand size-4" />
                                Find a list
                            </CardTitle>
                            <CardDescription>
                                Search the library, then open a list to work on
                                its members.
                            </CardDescription>
                        </CardHeader>

                        <CardContent>
                            <form
                                onSubmit={submitSearch}
                                className="flex items-end gap-2"
                            >
                                <div className="relative w-full">
                                    <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2" />
                                    <Input
                                        value={search}
                                        onChange={(event) =>
                                            setSearch(event.target.value)
                                        }
                                        placeholder="Search list names"
                                        className="pl-8"
                                    />
                                </div>

                                <Button type="submit" variant="secondary">
                                    Search
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                </div>

                <Card className="gap-0 overflow-hidden py-0">
                    <CardHeader className="flex flex-row items-center justify-between gap-2 border-b border-border/60 py-3">
                        <CardTitle className="text-sm font-medium">
                            {lists.length}{' '}
                            {lists.length === 1 ? 'list' : 'lists'}
                        </CardTitle>
                        <p className="text-muted-foreground text-xs">
                            Open a list to upload contacts into it
                        </p>
                    </CardHeader>

                    <CardContent className="px-0">
                        {lists.length === 0 ? (
                            <EmptyState
                                icon={ListChecks}
                                title={
                                    stats.lists === 0
                                        ? 'No contact lists yet'
                                        : 'No lists match this search'
                                }
                                description={
                                    stats.lists === 0
                                        ? 'Create a list, then upload a CSV into it or add contacts from the book.'
                                        : 'Try another name, or clear the search to see every list.'
                                }
                                action={
                                    can.create && stats.lists === 0 ? (
                                        <Button asChild>
                                            <Link href={urls.contacts.import}>
                                                <Upload />
                                                Upload contacts
                                            </Link>
                                        </Button>
                                    ) : undefined
                                }
                            />
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>List</TableHead>
                                        {clients.length > 0 && (
                                            <TableHead>Client</TableHead>
                                        )}
                                        <TableHead>Contacts</TableHead>
                                        <TableHead>Campaigns</TableHead>
                                        <TableHead>Updated</TableHead>
                                        <TableHead className="text-right">
                                            Actions
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>

                                <TableBody>
                                    {lists.map((list) => (
                                        <TableRow key={list.id}>
                                            <TableCell>
                                                <Link
                                                    href={urls.contacts.lists.show(
                                                        list.id,
                                                    )}
                                                    className="font-medium hover:underline"
                                                >
                                                    {list.name}
                                                </Link>
                                                <p className="text-muted-foreground text-xs">
                                                    {list.description ??
                                                        'No description'}
                                                </p>
                                            </TableCell>

                                            {clients.length > 0 && (
                                                <TableCell className="text-muted-foreground text-xs">
                                                    {list.client ?? '—'}
                                                </TableCell>
                                            )}

                                            <TableCell>
                                                {list.contacts_count}
                                            </TableCell>

                                            <TableCell className="text-muted-foreground text-xs">
                                                {list.campaigns_count}
                                            </TableCell>

                                            <TableCell className="text-muted-foreground text-xs">
                                                {formatDate(list.updated_at)}
                                            </TableCell>

                                            <TableCell className="text-right">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <Link
                                                        href={urls.contacts.lists.show(
                                                            list.id,
                                                        )}
                                                    >
                                                        Open
                                                    </Link>
                                                </Button>

                                                {can.delete && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            archive(list)
                                                        }
                                                    >
                                                        <Trash2 className="text-destructive" />
                                                        Delete
                                                    </Button>
                                                )}
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

ContactListsIndex.layout = {
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
