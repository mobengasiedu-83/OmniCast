import { Head, Link, router } from '@inertiajs/react';
import { Inbox, PlusCircle, Search } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import PageHeader from '@/components/page-header';
import Pagination from '@/components/pagination';
import StatCard from '@/components/stat-card';
import StatusBadge from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
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
import tickets from '@/routes/support/tickets';
import type { Paginated, SupportOption } from '@/types';

type TicketRow = {
    reference: string;
    subject: string;
    status: string;
    status_label: string;
    priority: string;
    priority_label: string;
    category: string | null;
    waiting_since: string;
    created_at: string | null;
    last_reply_at: string | null;
    url: string;
};

type PageProps = {
    tickets: Paginated<TicketRow>;
    filters: { status: string; search: string };
    statuses: SupportOption[];
    stats: { open: number; total: number; resolved: number };
};

/**
 * The organisation's ticket list.
 *
 * It is scoped to the signed-in client's organisation by the server, so there is
 * no tenant selector here: the only filters are the ones that help someone find a
 * conversation they already had.
 */
export default function TicketIndex({
    tickets: page,
    filters,
    statuses,
    stats,
}: PageProps) {
    const [search, setSearch] = useState(filters.search);
    const [status, setStatus] = useState(filters.status);

    const apply = (event?: FormEvent): void => {
        event?.preventDefault();

        router.get(
            tickets.index.url(),
            { search: search || null, status: status || null },
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="My tickets" />

            <div className="grid gap-6">
                <PageHeader
                    icon={Inbox}
                    title="My tickets"
                    description="Every conversation your organisation has open or settled with the support desk."
                    actions={
                        <Button asChild>
                            <Link href={tickets.create()}>
                                <PlusCircle />
                                New ticket
                            </Link>
                        </Button>
                    }
                />

                <div className="grid gap-3 sm:grid-cols-3">
                    <StatCard title="Open" value={stats.open} tone="warning" />
                    <StatCard
                        title="Resolved"
                        value={stats.resolved}
                        tone="success"
                    />
                    <StatCard
                        title="All tickets"
                        value={stats.total}
                        tone="neutral"
                    />
                </div>

                <Card className="gap-0 overflow-hidden py-0">
                    <form
                        onSubmit={apply}
                        className="flex flex-wrap items-center gap-2 border-b border-border/60 p-3"
                    >
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Reference, subject or message"
                            className="max-w-xs"
                        />

                        <Select
                            value={status || 'all'}
                            onValueChange={(value) =>
                                setStatus(value === 'all' ? '' : value)
                            }
                        >
                            <SelectTrigger className="w-44">
                                <SelectValue placeholder="All statuses" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All statuses</SelectItem>
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
                            <Search />
                            Filter
                        </Button>
                    </form>

                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Ticket</TableHead>
                                <TableHead>Category</TableHead>
                                <TableHead>Priority</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Last activity</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {page.data.map((ticket) => (
                                <TableRow key={ticket.reference}>
                                    <TableCell>
                                        <Link
                                            href={ticket.url}
                                            className="font-medium hover:text-brand"
                                        >
                                            {ticket.subject}
                                        </Link>
                                        <p className="text-xs text-muted-foreground">
                                            {ticket.reference}
                                        </p>
                                    </TableCell>
                                    <TableCell>
                                        {ticket.category ?? '—'}
                                    </TableCell>
                                    <TableCell className="capitalize">
                                        {ticket.priority_label}
                                    </TableCell>
                                    <TableCell>
                                        <StatusBadge
                                            status={ticket.status}
                                            label={ticket.status_label}
                                        />
                                    </TableCell>
                                    <TableCell className="text-xs whitespace-nowrap text-muted-foreground">
                                        {ticket.waiting_since}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>

                    {page.data.length === 0 && (
                        <p className="p-8 text-center text-sm text-muted-foreground">
                            No ticket matches these filters.
                        </p>
                    )}
                </Card>

                <Pagination paginator={page} noun="tickets" />
            </div>
        </>
    );
}
