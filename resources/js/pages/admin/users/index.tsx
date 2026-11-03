import { Head, router, useForm } from '@inertiajs/react';
import {
    MailWarning,
    Plus,
    Search,
    ShieldCheck,
    UserCheck,
    Users,
    UsersRound,
    UserX,
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
import { Textarea } from '@/components/ui/textarea';
import { urls } from '@/lib/urls';
import type {
    ClientOption,
    ManagedUserRow,
    Paginated,
    SelectOption,
} from '@/types';

type PageProps = {
    users: Paginated<ManagedUserRow>;
    filters: {
        search: string;
        role: string;
        status: string;
        client_id: string;
    };
    stats: {
        total: number;
        staff: number;
        active: number;
        suspended: number;
        mfa: number;
        unverified: number;
    };
    roles: SelectOption[];
    statuses: SelectOption[];
    clients: ClientOption[];
    can: {
        create: boolean;
    };
};

type UserFormValues = {
    name: string;
    email: string;
    job_title: string;
    client_id: string;
    role: string;
    password: string;
    password_confirmation: string;
};

type EditFormValues = Omit<
    UserFormValues,
    'password' | 'password_confirmation'
>;

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleString() : '—';
}

export default function AdminUsersIndex({
    users,
    filters,
    stats,
    roles,
    statuses,
    clients,
    can,
}: PageProps) {
    const [search, setSearch] = useState(filters.search);
    const [createOpen, setCreateOpen] = useState(false);
    const [editing, setEditing] = useState<ManagedUserRow | null>(null);
    const [suspending, setSuspending] = useState<ManagedUserRow | null>(null);

    const createForm = useForm<UserFormValues>({
        name: '',
        email: '',
        job_title: '',
        client_id: '',
        role: 'client-operator',
        password: '',
        password_confirmation: '',
    });

    const editForm = useForm<EditFormValues>({
        name: '',
        email: '',
        job_title: '',
        client_id: '',
        role: '',
    });

    const suspendForm = useForm<{ reason: string }>({ reason: '' });

    const hasFilters = Boolean(
        filters.search || filters.role || filters.status || filters.client_id,
    );

    const apply = (overrides: Record<string, string | null>): void => {
        router.get(
            urls.admin.users.index,
            {
                search: filters.search || null,
                role: filters.role || null,
                status: filters.status || null,
                client_id: filters.client_id || null,
                ...overrides,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const submitSearch = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        apply({ search: search || null });
    };

    const clearFilters = (): void => {
        setSearch('');
        apply({ search: null, role: null, status: null, client_id: null });
    };

    const openEdit = (user: ManagedUserRow): void => {
        editForm.setData({
            name: user.name,
            email: user.email,
            job_title: user.job_title ?? '',
            client_id: user.client_id === null ? '' : String(user.client_id),
            role: user.roles[0]?.name ?? 'client-operator',
        });
        editForm.clearErrors();
        setEditing(user);
    };

    const submitCreate = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        createForm.post(urls.admin.users.store, {
            preserveScroll: true,
            onSuccess: () => {
                createForm.reset();
                setCreateOpen(false);
            },
        });
    };

    const submitEdit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        if (editing === null) {
            return;
        }

        editForm.put(urls.admin.users.update(editing.id), {
            preserveScroll: true,
            onSuccess: () => setEditing(null),
        });
    };

    const submitSuspend = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        if (suspending === null) {
            return;
        }

        suspendForm.post(urls.admin.users.suspend(suspending.id), {
            preserveScroll: true,
            onSuccess: () => {
                suspendForm.reset();
                setSuspending(null);
            },
        });
    };

    const reactivate = (user: ManagedUserRow): void => {
        router.post(
            urls.admin.users.reactivate(user.id),
            {},
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Users" />

            <div className="flex flex-col gap-4">
                <PageHeader
                    icon={UsersRound}
                    title="All users"
                    description="Platform staff, client users, roles and access."
                    actions={
                        can.create && (
                            <Button onClick={() => setCreateOpen(true)}>
                                <Plus />
                                New user
                            </Button>
                        )
                    }
                />

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-5">
                    <StatCard
                        title="Total accounts"
                        value={stats.total}
                        icon={Users}
                        description={`${stats.staff} platform staff`}
                    />
                    <StatCard
                        title="Active"
                        value={stats.active}
                        icon={UserCheck}
                        tone="success"
                    />
                    <StatCard
                        title="Suspended"
                        value={stats.suspended}
                        icon={UserX}
                        tone={stats.suspended > 0 ? 'danger' : 'neutral'}
                    />
                    <StatCard
                        title="MFA enabled"
                        value={stats.mfa}
                        icon={ShieldCheck}
                        tone="signal"
                    />
                    <StatCard
                        title="Unverified email"
                        value={stats.unverified}
                        icon={MailWarning}
                        tone={stats.unverified > 0 ? 'warning' : 'neutral'}
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
                                    placeholder="Search name, email or title"
                                    className="pl-8"
                                />
                            </div>

                            <Button
                                type="submit"
                                variant="secondary"
                                size="icon"
                                aria-label="Search users"
                            >
                                <Search />
                            </Button>
                        </form>

                        <Select
                            value={filters.role || 'all'}
                            onValueChange={(value) =>
                                apply({ role: value === 'all' ? null : value })
                            }
                        >
                            <SelectTrigger className="w-44">
                                <SelectValue placeholder="All roles" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All roles</SelectItem>
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

                        <Select
                            value={filters.status || 'all'}
                            onValueChange={(value) =>
                                apply({
                                    status: value === 'all' ? null : value,
                                })
                            }
                        >
                            <SelectTrigger className="w-40">
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

                        <Select
                            value={filters.client_id || 'all'}
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
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={clearFilters}
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
                            {users.total}{' '}
                            {users.total === 1 ? 'account' : 'accounts'}
                        </CardTitle>
                        <CardDescription className="text-xs">
                            Page {users.current_page} of {users.last_page}
                        </CardDescription>
                    </CardHeader>

                    <CardContent className="px-0">
                        {users.data.length === 0 ? (
                            <EmptyState
                                icon={UsersRound}
                                title="No accounts match these filters"
                                description="Try a different search term or clear the filters."
                                action={
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={clearFilters}
                                    >
                                        Clear filters
                                    </Button>
                                }
                            />
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>User</TableHead>
                                        <TableHead>Role</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>MFA</TableHead>
                                        <TableHead>Last sign-in</TableHead>
                                        <TableHead className="text-right">
                                            Actions
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>

                                <TableBody>
                                    {users.data.map((user) => (
                                        <TableRow key={user.id}>
                                            <TableCell>
                                                <p className="font-medium">
                                                    {user.name}
                                                </p>
                                                <p className="text-muted-foreground text-xs">
                                                    {user.email}
                                                    {user.client
                                                        ? ` · ${user.client}`
                                                        : ' · Platform staff'}
                                                </p>
                                            </TableCell>

                                            <TableCell className="text-sm">
                                                {user.roles
                                                    .map((role) => role.label)
                                                    .join(', ') || '—'}
                                            </TableCell>

                                            <TableCell>
                                                <StatusBadge
                                                    status={
                                                        user.is_suspended
                                                            ? 'failed'
                                                            : user.is_active
                                                              ? 'active'
                                                              : 'draft'
                                                    }
                                                    label={
                                                        user.is_suspended
                                                            ? 'Suspended'
                                                            : user.is_active
                                                              ? 'Active'
                                                              : 'Inactive'
                                                    }
                                                />
                                                {user.suspension_reason && (
                                                    <p className="text-muted-foreground mt-1 max-w-56 text-xs">
                                                        {user.suspension_reason}
                                                    </p>
                                                )}
                                            </TableCell>

                                            <TableCell>
                                                <StatusBadge
                                                    status={
                                                        user.two_factor_enabled
                                                            ? 'active'
                                                            : 'draft'
                                                    }
                                                    label={
                                                        user.two_factor_enabled
                                                            ? 'Enabled'
                                                            : 'Off'
                                                    }
                                                />
                                            </TableCell>

                                            <TableCell className="text-muted-foreground text-xs">
                                                {formatDate(user.last_login_at)}
                                            </TableCell>

                                            <TableCell>
                                                <div className="flex items-center justify-end gap-1">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            openEdit(user)
                                                        }
                                                    >
                                                        Edit
                                                    </Button>

                                                    {user.is_suspended ? (
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            onClick={() =>
                                                                reactivate(user)
                                                            }
                                                        >
                                                            Reactivate
                                                        </Button>
                                                    ) : (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            className="text-destructive hover:text-destructive"
                                                            onClick={() => {
                                                                suspendForm.reset();
                                                                suspendForm.clearErrors();
                                                                setSuspending(
                                                                    user,
                                                                );
                                                            }}
                                                        >
                                                            Suspend
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

                <Pagination paginator={users} noun="accounts" />

                <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                    <DialogContent className="sm:max-w-lg">
                        <DialogHeader>
                            <DialogTitle>Create a user</DialogTitle>
                            <DialogDescription>
                                Platform staff need no client. Client roles must
                                belong to an organisation.
                            </DialogDescription>
                        </DialogHeader>

                        <form onSubmit={submitCreate} className="space-y-4">
                            <Field
                                label="Full name"
                                htmlFor="create-name"
                                error={createForm.errors.name}
                            >
                                <Input
                                    id="create-name"
                                    value={createForm.data.name}
                                    onChange={(event) =>
                                        createForm.setData(
                                            'name',
                                            event.target.value,
                                        )
                                    }
                                    autoComplete="name"
                                />
                            </Field>

                            <Field
                                label="Email"
                                htmlFor="create-email"
                                error={createForm.errors.email}
                            >
                                <Input
                                    id="create-email"
                                    type="email"
                                    value={createForm.data.email}
                                    onChange={(event) =>
                                        createForm.setData(
                                            'email',
                                            event.target.value,
                                        )
                                    }
                                    autoComplete="email"
                                />
                            </Field>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label>Role</Label>
                                    <Select
                                        value={createForm.data.role}
                                        onValueChange={(value) =>
                                            createForm.setData('role', value)
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
                                        message={createForm.errors.role}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label>Organisation</Label>
                                    <Select
                                        value={
                                            createForm.data.client_id || 'none'
                                        }
                                        onValueChange={(value) =>
                                            createForm.setData(
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
                                        message={createForm.errors.client_id}
                                    />
                                </div>
                            </div>

                            <Field
                                label="Job title"
                                htmlFor="create-job"
                                error={createForm.errors.job_title}
                            >
                                <Input
                                    id="create-job"
                                    value={createForm.data.job_title}
                                    onChange={(event) =>
                                        createForm.setData(
                                            'job_title',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Operations lead"
                                />
                            </Field>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    label="Password"
                                    htmlFor="create-password"
                                    error={createForm.errors.password}
                                >
                                    <Input
                                        id="create-password"
                                        type="password"
                                        value={createForm.data.password}
                                        onChange={(event) =>
                                            createForm.setData(
                                                'password',
                                                event.target.value,
                                            )
                                        }
                                        autoComplete="new-password"
                                    />
                                </Field>

                                <Field
                                    label="Confirm password"
                                    htmlFor="create-password-confirm"
                                >
                                    <Input
                                        id="create-password-confirm"
                                        type="password"
                                        value={
                                            createForm.data
                                                .password_confirmation
                                        }
                                        onChange={(event) =>
                                            createForm.setData(
                                                'password_confirmation',
                                                event.target.value,
                                            )
                                        }
                                        autoComplete="new-password"
                                    />
                                </Field>
                            </div>

                            <DialogFooter className="gap-2">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={() => setCreateOpen(false)}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={createForm.processing}
                                >
                                    Create user
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>

                <Dialog
                    open={editing !== null}
                    onOpenChange={(open) => {
                        if (!open) {
                            setEditing(null);
                        }
                    }}
                >
                    <DialogContent className="sm:max-w-lg">
                        <DialogHeader>
                            <DialogTitle>Edit {editing?.name}</DialogTitle>
                            <DialogDescription>
                                Role changes take effect on the user's next
                                request.
                            </DialogDescription>
                        </DialogHeader>

                        <form onSubmit={submitEdit} className="space-y-4">
                            <Field
                                label="Full name"
                                htmlFor="edit-name"
                                error={editForm.errors.name}
                            >
                                <Input
                                    id="edit-name"
                                    value={editForm.data.name}
                                    onChange={(event) =>
                                        editForm.setData(
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
                                        value={editForm.data.role}
                                        onValueChange={(value) =>
                                            editForm.setData('role', value)
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
                                        message={editForm.errors.role}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label>Organisation</Label>
                                    <Select
                                        value={editForm.data.client_id || 'none'}
                                        onValueChange={(value) =>
                                            editForm.setData(
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
                                        message={editForm.errors.client_id}
                                    />
                                </div>
                            </div>

                            <Field
                                label="Job title"
                                htmlFor="edit-job"
                                error={editForm.errors.job_title}
                            >
                                <Input
                                    id="edit-job"
                                    value={editForm.data.job_title}
                                    onChange={(event) =>
                                        editForm.setData(
                                            'job_title',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>

                            <DialogFooter className="gap-2">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={() => setEditing(null)}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={editForm.processing}
                                >
                                    Save changes
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>

                <Dialog
                    open={suspending !== null}
                    onOpenChange={(open) => {
                        if (!open) {
                            setSuspending(null);
                        }
                    }}
                >
                    <DialogContent className="sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle>
                                Suspend {suspending?.name}
                            </DialogTitle>
                            <DialogDescription>
                                The account is signed out everywhere and cannot
                                sign in until it is reactivated.
                            </DialogDescription>
                        </DialogHeader>

                        <form onSubmit={submitSuspend} className="space-y-4">
                            <Field
                                label="Reason"
                                htmlFor="suspend-reason"
                                error={suspendForm.errors.reason}
                            >
                                <Textarea
                                    id="suspend-reason"
                                    value={suspendForm.data.reason}
                                    onChange={(event) =>
                                        suspendForm.setData(
                                            'reason',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Repeated policy violations"
                                    rows={3}
                                />
                            </Field>

                            <DialogFooter className="gap-2">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={() => setSuspending(null)}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={suspendForm.processing}
                                >
                                    Suspend account
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>
        </>
    );
}

AdminUsersIndex.layout = {
    breadcrumbs: [
        {
            title: 'Users',
            href: urls.admin.users.index,
        },
    ],
};
