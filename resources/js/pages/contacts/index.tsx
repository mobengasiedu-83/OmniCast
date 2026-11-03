import { Head, Link, router } from '@inertiajs/react';
import { Ban, Plus, Search, ShieldCheck, Upload, Users } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import ClientFilter from '@/components/client-filter';
import EmptyState from '@/components/empty-state';
import PageHeader from '@/components/page-header';
import Pagination from '@/components/pagination';
import StatCard from '@/components/stat-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
} from '@/types';

type PageProps = {
    contacts: Paginated<ContactRow>;
    filters: {
        search: string;
        contact_list_id: number | null;
        consent_status: string;
    };
    contactLists: ContactListOption[];
    consentStatuses: SelectOption[];
    stats: {
        total: number;
        broadcastable: number;
        do_not_contact: number;
    };
    can: {
        create: boolean;
        import: boolean;
    };
    clients: ClientOption[];
    flash: {
        import: ImportResult | null;
    };
};

/** Consent reads green when reachable, grey when opted out. */
function consentTone(
    status: string,
): 'success' | 'warning' | 'muted' | 'outline' {
    if (status === 'opted_in' || status === 'subscribed') {
        return 'success';
    }

    if (status === 'opted_out' || status === 'unsubscribed') {
        return 'muted';
    }

    if (status === 'pending') {
        return 'warning';
    }

    return 'outline';
}

export default function ContactsIndex({
    contacts,
    filters,
    contactLists,
    consentStatuses,
    stats,
    can,
    clients,
    flash,
}: PageProps) {
    const [search, setSearch] = useState(filters.search);

    const apply = (overrides: Record<string, string | number | null>): void => {
        router.get(
            urls.contacts.index,
            {
                search: filters.search || null,
                contact_list_id: filters.contact_list_id,
                consent_status: filters.consent_status || null,
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

    return (
        <>
            <Head title="Contacts" />

            <div className="flex flex-col gap-4">
                <PageHeader
                    icon={Users}
                    title="Contacts"
                    description="Manage the people campaigns reach and their consent."
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

                <div className="grid gap-3 sm:grid-cols-3">
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
                        description="Consented and reachable"
                        icon={ShieldCheck}
                        tone="success"
                        className="animate-fade-in-up"
                        style={{ animationDelay: '60ms' }}
                    />
                    <StatCard
                        title="Do not contact"
                        value={stats.do_not_contact}
                        description="Suppressed from every campaign"
                        icon={Ban}
                        tone="danger"
                        className="animate-fade-in-up"
                        style={{ animationDelay: '120ms' }}
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

                        <Select
                            value={filters.consent_status || 'all'}
                            onValueChange={(value) =>
                                apply({
                                    consent_status:
                                        value === 'all' ? null : value,
                                })
                            }
                        >
                            <SelectTrigger className="w-40">
                                <SelectValue placeholder="Any consent" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Any consent</SelectItem>
                                {consentStatuses.map((status) => (
                                    <SelectItem
                                        key={status.value}
                                        value={status.value}
                                    >
                                        {status.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <ClientFilter
                            clients={clients}
                            value={null}
                            query={{
                                search: filters.search || null,
                                contact_list_id: filters.contact_list_id,
                                consent_status: filters.consent_status || null,
                            }}
                        />
                    </CardContent>
                </Card>

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
                                description="Adjust the search, list or consent filters to see contacts again."
                            />
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Contact</TableHead>
                                        <TableHead>Lists</TableHead>
                                        <TableHead>Consent</TableHead>
                                        <TableHead>Added</TableHead>
                                        <TableHead className="text-right">
                                            Actions
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>

                                <TableBody>{contacts.data.map((contact) => (
                                        <TableRow key={contact.id}>
                                            <TableCell>
                                                <div className="flex flex-col">
                                                    <span className="font-medium">
                                                        {contact.name}
                                                    </span>
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
                                                <div className="flex flex-wrap items-center gap-1.5">
                                                    <Badge
                                                        variant={consentTone(
                                                            contact.consent_status,
                                                        )}
                                                    >
                                                        {contact.consent_label}
                                                    </Badge>

                                                    {contact.is_do_not_contact && (
                                                        <Badge variant="destructive">
                                                            DNC
                                                        </Badge>
                                                    )}
                                                </div>
                                            </TableCell>

                                            <TableCell className="text-muted-foreground text-xs">
                                                {formatDate(
                                                    contact.created_at,
                                                )}
                                            </TableCell>

                                            <TableCell className="text-right">
                                                {can.create && (
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
