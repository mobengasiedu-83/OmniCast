import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    CheckCircle2,
    ClipboardCheck,
    LibraryBig,
    Search,
    ShieldX,
    X,
} from 'lucide-react';
import { useState, type FormEvent } from 'react';
import EmptyState from '@/components/empty-state';
import InputError from '@/components/input-error';
import PageHeader from '@/components/page-header';
import Pagination from '@/components/pagination';
import StatCard from '@/components/stat-card';
import StatusBadge from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { urls } from '@/lib/urls';
import type { Paginated, SelectOption, TemplateRow } from '@/types';

type PageProps = {
    templates: Paginated<TemplateRow>;
    filters: { search: string; channel: string; client_id: number | null };
    channels: SelectOption[];
    clients: { id: number; name: string }[];
    stats: { pending: number; approved_today: number; rejected: number };
    can: { approve: boolean };
};

export default function AdminTemplateApprovals({
    templates,
    filters,
    channels,
    clients,
    stats,
    can,
}: PageProps) {
    const [search, setSearch] = useState(filters.search);
    const [rejecting, setRejecting] = useState<TemplateRow | null>(null);
    const rejectForm = useForm({ reason: '' });

    const hasFilters = Boolean(
        filters.search || filters.channel || filters.client_id,
    );

    const apply = (overrides: Record<string, string | null>): void => {
        router.get(
            urls.admin.templateApprovals.index,
            {
                search: filters.search || null,
                channel: filters.channel || null,
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
        apply({ search: null, channel: null, client_id: null });
    };

    const approve = (template: TemplateRow): void => {
        router.post(
            urls.admin.templateApprovals.approve(template.id),
            {},
            { preserveScroll: true },
        );
    };

    const openReject = (template: TemplateRow): void => {
        rejectForm.reset();
        rejectForm.clearErrors();
        setRejecting(template);
    };

    const submitReject = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        if (!rejecting) {
            return;
        }

        rejectForm.post(urls.admin.templateApprovals.reject(rejecting.id), {
            preserveScroll: true,
            onSuccess: () => {
                setRejecting(null);
                rejectForm.reset();
            },
        });
    };

    return (
        <>
            <Head title="Template reviews" />

            <div className="flex flex-col gap-4">
                <PageHeader
                    icon={ClipboardCheck}
                    title="Template reviews"
                    description="Client templates waiting for the platform to sign the copy off. Approving makes the template active for its client; rejecting returns it with a reason."
                    actions={
                        <Button variant="outline" asChild>
                            <Link href={urls.admin.templates.index}>
                                <LibraryBig />
                                Catalogue
                            </Link>
                        </Button>
                    }
                />

                <div className="grid gap-3 sm:grid-cols-3">
                    <StatCard
                        title="Waiting for review"
                        value={stats.pending}
                        tone="warning"
                    />
                    <StatCard
                        title="Approved today"
                        value={stats.approved_today}
                        tone="success"
                    />
                    <StatCard
                        title="Returned overall"
                        value={stats.rejected}
                        tone="danger"
                    />
                </div>

                <Card className="py-3">
                    <CardContent className="flex flex-wrap items-center gap-2 px-4">
                        <form
                            onSubmit={submit}
                            className="flex flex-1 items-center gap-2 sm:flex-none"
                        >
                            <div className="relative w-full sm:w-64">
                                <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    placeholder="Search template or copy"
                                    className="pl-8"
                                />
                            </div>
                            <Button type="submit" variant="secondary">
                                Search
                            </Button>
                        </form>

                        <Select
                            value={filters.channel || 'all'}
                            onValueChange={(value) =>
                                apply({
                                    channel: value === 'all' ? null : value,
                                })
                            }
                        >
                            <SelectTrigger className="w-40">
                                <SelectValue placeholder="All channels" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    All channels
                                </SelectItem>
                                {channels.map((channel) => (
                                    <SelectItem
                                        key={channel.value}
                                        value={channel.value}
                                    >
                                        {channel.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <Select
                            value={
                                filters.client_id
                                    ? String(filters.client_id)
                                    : 'all'
                            }
                            onValueChange={(value) =>
                                apply({
                                    client_id: value === 'all' ? null : value,
                                })
                            }
                        >
                            <SelectTrigger className="w-48">
                                <SelectValue placeholder="All clients" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All clients</SelectItem>
                                {clients.map((client) => (
                                    <SelectItem
                                        key={client.id}
                                        value={String(client.id)}
                                    >
                                        {client.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        {hasFilters && (
                            <Button variant="ghost" size="sm" onClick={clear}>
                                <X className="size-4" />
                                Clear
                            </Button>
                        )}
                    </CardContent>
                </Card>

                {templates.data.length === 0 ? (
                    <Card>
                        <EmptyState
                            icon={CheckCircle2}
                            title="Nothing waiting for review"
                            description="Client templates appear here the moment they are submitted. You can safely leave this page."
                            action={
                                hasFilters && (
                                    <Button variant="outline" onClick={clear}>
                                        Clear filters
                                    </Button>
                                )
                            }
                        />
                    </Card>
                ) : (
                    <div className="grid gap-3 xl:grid-cols-2">
                        {templates.data.map((template) => (
                            <Card key={template.id}>
                                <CardHeader className="gap-2">
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="min-w-0">
                                            <CardTitle className="truncate text-sm">
                                                {template.name}
                                            </CardTitle>
                                            <CardDescription className="mt-1 text-xs">
                                                {template.client ??
                                                    'Unknown client'}
                                                {template.category
                                                    ? ` · ${template.category.name}`
                                                    : ''}
                                                {template.author
                                                    ? ` · ${template.author}`
                                                    : ''}
                                            </CardDescription>
                                        </div>
                                        <StatusBadge
                                            status={template.status}
                                            label={template.status_label}
                                        />
                                    </div>
                                    <div className="flex flex-wrap items-center gap-1.5">
                                        <Badge variant="outline">
                                            {template.channel_label}
                                        </Badge>
                                        <Badge variant="muted">
                                            {template.language.toUpperCase()}
                                        </Badge>
                                    </div>
                                </CardHeader>
                                <CardContent className="flex flex-1 flex-col gap-3">
                                    {template.subject && (
                                        <p className="text-xs font-semibold">
                                            {template.subject}
                                        </p>
                                    )}
                                    <p className="line-clamp-4 text-sm whitespace-pre-wrap text-muted-foreground">
                                        {template.body}
                                    </p>

                                    {template.variables.length > 0 && (
                                        <div className="flex flex-wrap gap-1">
                                            {template.variables.map(
                                                (variable) => (
                                                    <span
                                                        key={variable}
                                                        className="rounded bg-muted px-1.5 py-0.5 font-mono text-[10px] text-muted-foreground"
                                                    >
                                                        {variable}
                                                    </span>
                                                ),
                                            )}
                                        </div>
                                    )}

                                    {can.approve && (
                                        <div className="mt-auto flex flex-wrap items-center gap-2 pt-1">
                                            <Button
                                                size="sm"
                                                onClick={() =>
                                                    approve(template)
                                                }
                                            >
                                                <CheckCircle2 />
                                                Approve
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    openReject(template)
                                                }
                                            >
                                                <ShieldX />
                                                Return to author
                                            </Button>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}

                <Pagination paginator={templates} noun="templates" />

                <Dialog
                    open={rejecting !== null}
                    onOpenChange={(open) => !open && setRejecting(null)}
                >
                    <DialogContent>
                        <form onSubmit={submitReject} className="grid gap-4">
                            <DialogHeader>
                                <DialogTitle>
                                    Return {rejecting?.name}
                                </DialogTitle>
                                <DialogDescription>
                                    The client sees this reason and can correct
                                    the copy before resubmitting it.
                                </DialogDescription>
                            </DialogHeader>

                            <div className="grid gap-1.5">
                                <Label htmlFor="reason">
                                    Reason for returning
                                </Label>
                                <Textarea
                                    id="reason"
                                    rows={3}
                                    value={rejectForm.data.reason}
                                    onChange={(event) =>
                                        rejectForm.setData(
                                            'reason',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Explain what the client needs to change before the copy can be approved."
                                />
                                <InputError
                                    message={rejectForm.errors.reason}
                                />
                            </div>

                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setRejecting(null)}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={rejectForm.processing}
                                >
                                    {rejectForm.processing
                                        ? 'Returning…'
                                        : 'Return template'}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>
        </>
    );
}

AdminTemplateApprovals.layout = {
    breadcrumbs: [
        {
            title: 'Catalogue',
            href: urls.admin.templates.index,
        },
        {
            title: 'Template reviews',
            href: urls.admin.templateApprovals.index,
        },
    ],
};
