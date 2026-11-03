import { Head, router, useForm } from '@inertiajs/react';
import {
    CheckCircle2,
    Clock,
    MailPlus,
    RefreshCw,
    Search,
    ShieldAlert,
    Trash2,
    UserRoundX,
    X,
} from 'lucide-react';
import { useState, type FormEvent } from 'react';
import EmptyState from '@/components/empty-state';
import Field from '@/components/form-field';
import InputError from '@/components/input-error';
import PageHeader from '@/components/page-header';
import Pagination from '@/components/pagination';
import StatCard from '@/components/stat-card';
import StatusBadge from '@/components/status-badge';
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
    InvitationRow,
    Paginated,
    SelectOption,
} from '@/types';

type PageProps = {
    invitations: Paginated<InvitationRow>;
    filters: { search: string; status: string };
    stats: {
        pending: number;
        accepted: number;
        expired: number;
        revoked: number;
    };
    statuses: SelectOption[];
    roles: SelectOption[];
    clients: ClientOption[];
};

type InviteFormValues = {
    email: string;
    name: string;
    role: string;
    client_id: string;
};

function formatDate(value: string | null | undefined): string {
    return value ? new Date(value).toLocaleString() : '—';
}

export default function AdminInvitationsIndex({
    invitations,
    filters,
    stats,
    statuses,
    roles,
    clients,
}: PageProps) {
    const [search, setSearch] = useState(filters.search);
    const [inviteOpen, setInviteOpen] = useState(false);

    const inviteForm = useForm<InviteFormValues>({
        email: '',
        name: '',
        role: 'client-operator',
        client_id: '',
    });

    const hasFilters = Boolean(filters.search || filters.status);

    const apply = (overrides: Record<string, string | null>): void => {
        router.get(
            urls.admin.invitations.index,
            {
                search: filters.search || null,
                status: filters.status || null,
                ...overrides,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const submitSearch = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        apply({ search: search || null });
    };

    const submitInvite = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        inviteForm.post(urls.admin.invitations.store, {
            preserveScroll: true,
            onSuccess: () => {
                inviteForm.reset();
                setInviteOpen(false);
            },
        });
    };

    const resend = (invitation: InvitationRow): void => {
        router.post(
            urls.admin.invitations.resend(invitation.id),
            {},
            { preserveScroll: true },
        );
    };

    const revoke = (invitation: InvitationRow): void => {
        router.post(
            urls.admin.invitations.revoke(invitation.id),
            {},
            { preserveScroll: true },
        );
    };

    const remove = (invitation: InvitationRow): void => {
        if (!window.confirm(`Delete the invitation for ${invitation.email}?`)) {
            return;
        }

        router.delete(urls.admin.invitations.destroy(invitation.id), {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Invitations" />

            <div className="flex flex-col gap-4">
                <PageHeader
                    icon={MailPlus}
                    title="Invitations"
                    description="Invite staff and client users, then track acceptance."
                    actions={
                        <Button onClick={() => setInviteOpen(true)}>
                            <MailPlus />
                            New invitation
                        </Button>
                    }
                />

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        title="Pending"
                        value={stats.pending}
                        icon={Clock}
                        tone="warning"
                    />
                    <StatCard
                        title="Accepted"
                        value={stats.accepted}
                        icon={CheckCircle2}
                        tone="success"
                    />
                    <StatCard
                        title="Expired"
                        value={stats.expired}
                        icon={UserRoundX}
                        tone={stats.expired > 0 ? 'danger' : 'neutral'}
                    />
                    <StatCard
                        title="Revoked"
                        value={stats.revoked}
                        icon={ShieldAlert}
                        tone="neutral"
                    />
                </div>

                <Card className="py-3">
                    <CardContent className="flex flex-wrap items-center gap-2 px-4">
                        <form
                            onSubmit={submitSearch}
                            className="flex flex-1 items-center gap-2 sm:flex-none"
                        >
                            <div className="relative w-full sm:w-72">
                                <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2" />
                                <Input
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    placeholder="Search email or name"
                                    className="pl-8"
                                />
                            </div>

                            <Button
                                type="submit"
                                variant="secondary"
                                size="icon"
                                aria-label="Search invitations"
                            >
                                <Search />
                            </Button>
                        </form>

                        <Select
                            value={filters.status || 'all'}
                            onValueChange={(value) =>
                                apply({ status: value === 'all' ? null : value })
                            }
                        >
                            <SelectTrigger className="w-44">
                                <SelectValue placeholder="All statuses" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    All statuses
                                </SelectItem>
                                {statuses.map((status) => (
                                    <SelectItem
                                        key={status.value}
                                        value={status.value}
                                    >
                                        {status.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        {hasFilters && (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => {
                                    setSearch('');
                                    apply({ search: null, status: null });
                                }}
                            >
                                <X />
                                Clear
                            </Button>
                        )}
                    </CardContent>
                </Card>


                <Card className="overflow-hidden">
                    <CardHeader className="flex flex-row items-center justify-between gap-2 border-b border-border/60 py-3">
                        <CardTitle className="text-sm font-medium">
                            {invitations.total}{' '}
                            {invitations.total === 1
                                ? 'invitation'
                                : 'invitations'}
                        </CardTitle>
                        <CardDescription className="text-xs">
                            Page {invitations.current_page} of{' '}
                            {invitations.last_page}
                        </CardDescription>
                    </CardHeader>

                    <CardContent className="px-0">
                        {invitations.data.length === 0 ? (
                            <EmptyState
                                icon={MailPlus}
                                title="No invitations yet"
                                description="Invite a colleague or a client user to get started."
                                action={
                                    <Button
                                        size="sm"
                                        onClick={() => setInviteOpen(true)}
                                    >
                                        New invitation
                                    </Button>
                                }
                            />
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Invitee</TableHead>
                                        <TableHead>Role</TableHead>
                                        <TableHead>Organisation</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Expires</TableHead>
                                        <TableHead className="text-right">
                                            Actions
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>

                                <TableBody>
                                    {invitations.data.map((invitation) => (
                                        <TableRow key={invitation.id}>
                                            <TableCell>
                                                <p className="font-medium">
                                                    {invitation.name ??
                                                        invitation.email}
                                                </p>
                                                <p className="text-muted-foreground text-xs">
                                                    {invitation.email}
                                                    {invitation.inviter
                                                        ? ` · invited by ${invitation.inviter}`
                                                        : ''}
                                                </p>
                                            </TableCell>

                                            <TableCell className="text-sm">
                                                {invitation.role_label}
                                            </TableCell>

                                            <TableCell className="text-sm">
                                                {invitation.client ??
                                                    'Platform staff'}
                                            </TableCell>


                                            <TableCell>
                                                <StatusBadge
                                                    status={invitation.status}
                                                    label={
                                                        invitation.status_label
                                                    }
                                                />
                                            </TableCell>

                                            <TableCell className="text-muted-foreground text-xs">
                                                {formatDate(
                                                    invitation.expires_at,
                                                )}
                                            </TableCell>

                                            <TableCell>
                                                <div className="flex items-center justify-end gap-1">
                                                    {invitation.is_pending ? (
                                                        <>
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                onClick={() =>
                                                                    resend(
                                                                        invitation,
                                                                    )
                                                                }
                                                            >
                                                                <RefreshCw />
                                                                Resend
                                                            </Button>
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                className="text-destructive hover:text-destructive"
                                                                onClick={() =>
                                                                    revoke(
                                                                        invitation,
                                                                    )
                                                                }
                                                            >
                                                                <UserRoundX />
                                                                Revoke
                                                            </Button>
                                                        </>
                                                    ) : (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            className="text-destructive hover:text-destructive"
                                                            onClick={() =>
                                                                remove(
                                                                    invitation,
                                                                )
                                                            }
                                                        >
                                                            <Trash2 />
                                                            Delete
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

                <Pagination paginator={invitations} noun="invitations" />


                <Dialog open={inviteOpen} onOpenChange={setInviteOpen}>
                    <DialogContent className="sm:max-w-lg">
                        <DialogHeader>
                            <DialogTitle>Invite a user</DialogTitle>
                            <DialogDescription>
                                The invitee receives a one-time link, sets a
                                password and is signed in automatically.
                            </DialogDescription>
                        </DialogHeader>

                        <form onSubmit={submitInvite} className="space-y-4">
                            <Field
                                label="Email"
                                htmlFor="invite-email"
                                error={inviteForm.errors.email}
                            >
                                <Input
                                    id="invite-email"
                                    type="email"
                                    value={inviteForm.data.email}
                                    onChange={(event) =>
                                        inviteForm.setData(
                                            'email',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="name@company.com"
                                />
                            </Field>

                            <Field
                                label="Name (optional)"
                                htmlFor="invite-name"
                                error={inviteForm.errors.name}
                            >
                                <Input
                                    id="invite-name"
                                    value={inviteForm.data.name}
                                    onChange={(event) =>
                                        inviteForm.setData(
                                            'name',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label>Role</Label>
                                    <Select
                                        value={inviteForm.data.role}
                                        onValueChange={(value) =>
                                            inviteForm.setData('role', value)
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select a role" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {roles.map((role) => (
                                                <SelectItem
                                                    key={role.value}
                                                    value={role.value}
                                                >
                                                    {role.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        message={inviteForm.errors.role}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label>Organisation</Label>
                                    <Select
                                        value={inviteForm.data.client_id || 'none'}
                                        onValueChange={(value) =>
                                            inviteForm.setData(
                                                'client_id',
                                                value === 'none' ? '' : value,
                                            )
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Platform staff" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">
                                                Platform staff
                                            </SelectItem>
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
                                    <InputError
                                        message={inviteForm.errors.client_id}
                                    />
                                </div>
                            </div>


                            <DialogFooter className="gap-2">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={() => setInviteOpen(false)}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={inviteForm.processing}
                                >
                                    Send invitation
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>
        </>
    );
}

AdminInvitationsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Users',
            href: urls.admin.users.index,
        },
        {
            title: 'Invitations',
            href: urls.admin.invitations.index,
        },
    ],
};

