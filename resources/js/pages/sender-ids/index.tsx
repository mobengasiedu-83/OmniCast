import { Head, Link, router, useForm } from '@inertiajs/react';
import { CheckCircle2, Fingerprint, Pencil, Plus, Search, ShieldX, Trash2, X } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { useConfirm } from '@/components/confirm-dialog';
import EmptyState from '@/components/empty-state';
import InputError from '@/components/input-error';
import PageHeader from '@/components/page-header';
import Pagination from '@/components/pagination';
import StatCard from '@/components/stat-card';
import StatusBadge from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { urls } from '@/lib/urls';
import type { ClientOption, Paginated, SelectOption, SenderIdRow } from '@/types';

type PageProps = {
    senderIds: Paginated<SenderIdRow>;
    filters: { search: string; channel: string; status: string; client_id: number | null };
    stats: { total: number; pending: number; approved: number; rejected: number };
    channels: SelectOption[];
    statuses: SelectOption[];
    clients: ClientOption[];
    can: { create: boolean; approve: boolean };
};

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleDateString() : '—';
}

export default function SenderIdsIndex({ senderIds, filters, stats, channels, statuses, clients, can }: PageProps) {
    const [search, setSearch] = useState(filters.search);
    const [rejecting, setRejecting] = useState<SenderIdRow | null>(null);
    const rejectForm = useForm({ reason: '' });
    const confirm = useConfirm();

    const hasFilters = Boolean(filters.search || filters.channel || filters.status || filters.client_id);

    const apply = (overrides: Record<string, string | null>): void => {
        router.get(
            urls.senderIds.index,
            {
                search: filters.search || null,
                channel: filters.channel || null,
                status: filters.status || null,
                client_id: filters.client_id ? String(filters.client_id) : null,
                ...overrides,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        apply({ search: search || null });
    };

    const clear = (): void => {
        setSearch('');
        apply({ search: null, channel: null, status: null, client_id: null });
    };

    const approve = (senderId: SenderIdRow): void => {
        router.post(urls.senderIds.approve(senderId.id), {}, { preserveScroll: true });
    };

    const openReject = (senderId: SenderIdRow): void => {
        rejectForm.reset();
        rejectForm.clearErrors();
        setRejecting(senderId);
    };

    const submitReject = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        if (!rejecting) {
            return;
        }

        rejectForm.post(urls.senderIds.reject(rejecting.id), {
            preserveScroll: true,
            onSuccess: () => {
                setRejecting(null);
                rejectForm.reset();
            },
        });
    };

    const withdraw = async (senderId: SenderIdRow): Promise<void> => {
        const confirmed = await confirm({
            title: `Withdraw ${senderId.name}?`,
            description:
                'The request is removed from the review queue. This cannot be undone.',
            confirmLabel: 'Withdraw request',
            cancelLabel: 'Keep request',
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        router.delete(urls.senderIds.destroy(senderId.id), {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Sender IDs" />

            <div className="flex flex-col gap-4">
                <PageHeader
                    icon={Fingerprint}
                    title="Sender IDs"
                    description="Request and track the SMS sender IDs, email from addresses and voice caller numbers your campaigns broadcast as."
                    actions={
                        can.create && (
                            <Button asChild>
                                <Link href={urls.senderIds.create}>
                                    <Plus />
                                    Request sender ID
                                </Link>
                            </Button>
                        )
                    }
                />

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard title="All identities" value={stats.total} icon={Fingerprint} />
                    <StatCard title="Awaiting review" value={stats.pending} tone="warning" />
                    <StatCard title="Approved" value={stats.approved} tone="success" />
                    <StatCard title="Rejected" value={stats.rejected} tone="danger" />
                </div>

                <Card className="py-3">
                    <CardContent className="flex flex-wrap items-center gap-2 px-4">
                        <form onSubmit={submit} className="flex flex-1 items-center gap-2 sm:flex-none">
                            <div className="relative w-full sm:w-64">
                                <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2" />
                                <Input
                                    value={search}
                                    onChange={(event) => setSearch(event.target.value)}
                                    placeholder="Search identity or use case"
                                    className="pl-8"
                                />
                            </div>

                            <Button type="submit" variant="secondary" size="icon" aria-label="Search sender IDs">
                                <Search />
                            </Button>
                        </form>

                        <Select
                            value={filters.channel || 'all'}
                            onValueChange={(value) => apply({ channel: value === 'all' ? null : value })}
                        >
                            <SelectTrigger className="w-40">
                                <SelectValue placeholder="All channels" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All channels</SelectItem>
                                {channels.map((channel) => (
                                    <SelectItem key={channel.value} value={channel.value}>
                                        {channel.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <Select
                            value={filters.status || 'all'}
                            onValueChange={(value) => apply({ status: value === 'all' ? null : value })}
                        >
                            <SelectTrigger className="w-44">
                                <SelectValue placeholder="All statuses" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All statuses</SelectItem>
                                {statuses.map((status) => (
                                    <SelectItem key={status.value} value={status.value}>
                                        {status.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        {clients.length > 0 && (
                            <Select
                                value={filters.client_id ? String(filters.client_id) : 'all'}
                                onValueChange={(value) => apply({ client_id: value === 'all' ? null : value })}
                            >
                                <SelectTrigger className="w-48">
                                    <SelectValue placeholder="All clients" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All clients</SelectItem>
                                    {clients.map((client) => (
                                        <SelectItem key={client.id} value={String(client.id)}>
                                            {client.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}

                        {hasFilters && (
                            <Button variant="ghost" size="sm" onClick={clear} className="text-muted-foreground">
                                <X />
                                Clear
                            </Button>
                        )}
                    </CardContent>
                </Card>

                <Card className="gap-0 overflow-hidden py-0">
                    <CardHeader className="flex flex-row items-center justify-between gap-2 border-b border-border/60 py-3">
                        <CardTitle className="text-sm font-medium">
                            {senderIds.total} {senderIds.total === 1 ? 'sender ID' : 'sender IDs'}
                        </CardTitle>
                        <CardDescription className="text-xs">
                            Page {senderIds.current_page} of {senderIds.last_page}
                        </CardDescription>
                    </CardHeader>

                    <CardContent className="p-0">
                        {senderIds.data.length === 0 ? (
                            <EmptyState
                                icon={Fingerprint}
                                title="No sender IDs yet"
                                description="Request an SMS sender ID, email from address or voice caller number to broadcast with a recognised identity."
                                action={
                                    can.create ? (
                                        <Button asChild>
                                            <Link href={urls.senderIds.create}>
                                                <Plus />
                                                Request sender ID
                                            </Link>
                                        </Button>
                                    ) : undefined
                                }
                            />
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Sender ID</TableHead>
                                        {clients.length > 0 && <TableHead>Client</TableHead>}
                                        <TableHead>Use case</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Requested</TableHead>
                                        <TableHead className="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {senderIds.data.map((senderId) => (
                                        <TableRow key={senderId.id}>
                                            <TableCell>
                                                <p className="font-medium">{senderId.name}</p>
                                                <p className="text-muted-foreground text-xs">
                                                    {senderId.channel_label}
                                                    {senderId.country ? ` · ${senderId.country}` : ''}
                                                    {senderId.requester ? ` · ${senderId.requester}` : ''}
                                                </p>
                                            </TableCell>

                                            {clients.length > 0 && (
                                                <TableCell className="text-sm">{senderId.client ?? '—'}</TableCell>
                                            )}

                                            <TableCell className="text-muted-foreground max-w-64 text-sm">
                                                {senderId.purpose ?? '—'}
                                            </TableCell>

                                            <TableCell>
                                                <StatusBadge status={senderId.status} label={senderId.status_label} />
                                                {senderId.rejection_reason && (
                                                    <p className="text-muted-foreground mt-1 max-w-56 text-xs">
                                                        {senderId.rejection_reason}
                                                    </p>
                                                )}
                                            </TableCell>

                                            <TableCell className="text-muted-foreground text-xs">
                                                {formatDate(senderId.requested_at)}
                                            </TableCell>

                                            <TableCell>
                                                <div className="flex flex-wrap items-center justify-end gap-1">
                                                    {senderId.can.approve && (
                                                        <>
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                                onClick={() => approve(senderId)}
                                                            >
                                                                <CheckCircle2 />
                                                                Approve
                                                            </Button>
                                                            <Button
                                                                size="sm"
                                                                variant="ghost"
                                                                onClick={() => openReject(senderId)}
                                                            >
                                                                <ShieldX />
                                                                Reject
                                                            </Button>
                                                        </>
                                                    )}

                                                    {senderId.can.update && (
                                                        <Button size="sm" variant="ghost" asChild>
                                                            <Link href={urls.senderIds.edit(senderId.id)}>
                                                                <Pencil />
                                                                Edit
                                                            </Link>
                                                        </Button>
                                                    )}

                                                    {senderId.can.delete && (
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            onClick={() =>
                                                                void withdraw(senderId)
                                                            }
                                                        >
                                                            <Trash2 />
                                                            Withdraw
                                                        </Button>
                                                    )}
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>

                <Pagination paginator={senderIds} noun="sender IDs" />

                <Dialog open={rejecting !== null} onOpenChange={(open) => !open && setRejecting(null)}>
                    <DialogContent>
                        <form onSubmit={submitReject} className="grid gap-4">
                            <DialogHeader>
                                <DialogTitle>Reject {rejecting?.name}</DialogTitle>
                                <DialogDescription>
                                    The client sees this reason and can correct the request before resubmitting it.
                                </DialogDescription>
                            </DialogHeader>

                            <div className="grid gap-1.5">
                                <Label htmlFor="reason">Reason for rejection</Label>
                                <Textarea
                                    id="reason"
                                    rows={3}
                                    value={rejectForm.data.reason}
                                    onChange={(event) => rejectForm.setData('reason', event.target.value)}
                                    placeholder="Explain what compliance needs the client to change."
                                />
                                <InputError message={rejectForm.errors.reason} />
                            </div>

                            <DialogFooter>
                                <Button type="button" variant="outline" onClick={() => setRejecting(null)}>
                                    Cancel
                                </Button>
                                <Button type="submit" variant="destructive" disabled={rejectForm.processing}>
                                    {rejectForm.processing ? 'Rejecting…' : 'Reject request'}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>
        </>
    );
}

SenderIdsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Sender IDs',
            href: urls.senderIds.index,
        },
    ],
};
