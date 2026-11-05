import { Head, router } from '@inertiajs/react';
import { LogOut, MonitorSmartphone, Search, Users, X } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { useConfirm } from '@/components/confirm-dialog';
import EmptyState from '@/components/empty-state';
import PageHeader from '@/components/page-header';
import Pagination from '@/components/pagination';
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { urls } from '@/lib/urls';
import type { Paginated, PlatformSessionRow } from '@/types';

type PageProps = {
    sessions: Paginated<PlatformSessionRow>;
    filters: { search: string };
    stats: {
        sessions: number;
        users: number;
        guests: number;
    };
    supported: boolean;
};

export default function AdminSessions({
    sessions,
    filters,
    stats,
    supported,
}: PageProps) {
    const [search, setSearch] = useState(filters.search);

    const confirm = useConfirm();

    const submitSearch = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        router.get(
            urls.admin.sessions.index,
            { search: search || null },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const signOut = async (row: PlatformSessionRow): Promise<void> => {
        if (row.user_id === null) {
            return;
        }

        const confirmed = await confirm({
            title: row.user_name
                ? `Sign ${row.user_name} out of this device?`
                : 'Sign this session out?',
            description:
                'The device is signed out immediately and the person has to sign in again.',
            confirmLabel: 'Sign out session',
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        router.delete(
            urls.admin.sessions.revokeOne(row.user_id, row.session_id),
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Sessions" />

            <div className="flex flex-col gap-4">
                <PageHeader
                    icon={MonitorSmartphone}
                    title="Sessions"
                    description="Who is signed in right now and from where."
                />

                <div className="grid gap-3 sm:grid-cols-3">
                    <StatCard
                        title="Live sessions"
                        value={stats.sessions}
                        icon={MonitorSmartphone}
                    />
                    <StatCard
                        title="Signed-in users"
                        value={stats.users}
                        icon={Users}
                        tone="success"
                    />
                    <StatCard
                        title="Guest sessions"
                        value={stats.guests}
                        icon={Users}
                        tone="neutral"
                        description="Signed out or pre-login"
                    />
                </div>

                {!supported && (
                    <Card className="border-warning/30 bg-warning-soft">
                        <CardContent className="px-4 py-3 text-sm">
                            Sessions are not stored in the database, so this
                            monitor cannot read them. Set
                            <code className="mx-1 rounded bg-black/5 px-1 py-0.5 text-xs">
                                SESSION_DRIVER=database
                            </code>
                            to enable it.
                        </CardContent>
                    </Card>
                )}

                <Card className="py-3">
                    <CardContent className="flex flex-wrap items-center gap-2 px-4">
                        <form
                            onSubmit={submitSearch}
                            className="flex flex-1 items-center gap-2 sm:flex-none"
                        >
                            <div className="relative w-full sm:w-72">
                                <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    placeholder="Search user, client or IP"
                                    className="pl-8"
                                />
                            </div>

                            <Button
                                type="submit"
                                variant="secondary"
                                size="icon"
                                aria-label="Search sessions"
                            >
                                <Search />
                            </Button>
                        </form>

                        {filters.search && (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => {
                                    setSearch('');
                                    router.get(
                                        urls.admin.sessions.index,
                                        {},
                                        {
                                            preserveState: true,
                                            preserveScroll: true,
                                            replace: true,
                                        },
                                    );
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
                            {sessions.total}{' '}
                            {sessions.total === 1 ? 'session' : 'sessions'}
                        </CardTitle>
                        <CardDescription className="text-xs">
                            Page {sessions.current_page} of {sessions.last_page}
                        </CardDescription>
                    </CardHeader>

                    <CardContent className="px-0">
                        {sessions.data.length === 0 ? (
                            <EmptyState
                                icon={MonitorSmartphone}
                                title="No live sessions"
                                description="Sessions appear here as soon as somebody signs in."
                            />
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>User</TableHead>
                                        <TableHead>Client</TableHead>
                                        <TableHead>Device</TableHead>
                                        <TableHead>IP address</TableHead>
                                        <TableHead>Last active</TableHead>
                                        <TableHead className="text-right">
                                            Actions
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>

                                <TableBody>
                                    {sessions.data.map((session) => (
                                        <TableRow key={session.session_id}>
                                            <TableCell>
                                                <p className="font-medium">
                                                    {session.user_name ??
                                                        'Unknown user'}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {session.user_email}
                                                </p>
                                            </TableCell>

                                            <TableCell className="text-sm">
                                                {session.client_name ??
                                                    'Platform staff'}
                                            </TableCell>

                                            <TableCell className="text-sm">
                                                {session.device}
                                            </TableCell>

                                            <TableCell className="font-mono text-xs text-muted-foreground">
                                                {session.ip_address ?? '—'}
                                            </TableCell>

                                            <TableCell className="text-xs text-muted-foreground">
                                                {new Date(
                                                    session.last_active_at,
                                                ).toLocaleString()}
                                            </TableCell>

                                            <TableCell className="text-right">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    disabled={
                                                        session.user_id === null
                                                    }
                                                    onClick={() =>
                                                        void signOut(session)
                                                    }
                                                >
                                                    <LogOut />
                                                    Sign out
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>

                <Pagination paginator={sessions} noun="sessions" />
            </div>
        </>
    );
}

AdminSessions.layout = {
    breadcrumbs: [
        {
            title: 'Users',
            href: urls.admin.users.index,
        },
        {
            title: 'Sessions',
            href: urls.admin.sessions.index,
        },
    ],
};
