import { Head, router } from '@inertiajs/react';
import {
    AlertTriangle,
    Globe2,
    Search,
    ScrollText,
    ShieldAlert,
    X,
} from 'lucide-react';
import { useState, type FormEvent } from 'react';
import EmptyState from '@/components/empty-state';
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
import type { LoginAuditRow, Paginated, SelectOption } from '@/types';

type PageProps = {
    audits: Paginated<LoginAuditRow>;
    filters: { search: string; event: string };
    events: SelectOption[];
    stats: {
        today: number;
        failures_today: number;
        blocked: number;
        unique_ips: number;
    };
};

export default function AdminLoginAudit({
    audits,
    filters,
    events,
    stats,
}: PageProps) {
    const [search, setSearch] = useState(filters.search);

    const hasFilters = Boolean(filters.search || filters.event);

    const apply = (overrides: Record<string, string | null>): void => {
        router.get(
            urls.admin.loginAudit.index,
            {
                search: filters.search || null,
                event: filters.event || null,
                ...overrides,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const submitSearch = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        apply({ search: search || null });
    };

    return (
        <>
            <Head title="Login audit" />

            <div className="flex flex-col gap-4">
                <PageHeader
                    icon={ScrollText}
                    title="Login audit"
                    description="Every sign-in, sign-out and rejected attempt, newest first."
                />

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        title="Events today"
                        value={stats.today}
                        icon={ScrollText}
                    />
                    <StatCard
                        title="Failures today"
                        value={stats.failures_today}
                        icon={AlertTriangle}
                        tone={stats.failures_today > 0 ? 'warning' : 'neutral'}
                    />
                    <StatCard
                        title="Blocked (7 days)"
                        value={stats.blocked}
                        icon={ShieldAlert}
                        tone={stats.blocked > 0 ? 'danger' : 'neutral'}
                    />
                    <StatCard
                        title="Unique IPs (24h)"
                        value={stats.unique_ips}
                        icon={Globe2}
                        tone="signal"
                    />
                </div>

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
                                    placeholder="Search email, name or IP"
                                    className="pl-8"
                                />
                            </div>

                            <Button
                                type="submit"
                                variant="secondary"
                                size="icon"
                                aria-label="Search audit trail"
                            >
                                <Search />
                            </Button>
                        </form>

                        <Select
                            value={filters.event || 'all'}
                            onValueChange={(value) =>
                                apply({ event: value === 'all' ? null : value })
                            }
                        >
                            <SelectTrigger className="w-48">
                                <SelectValue placeholder="All events" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All events</SelectItem>
                                {events.map((event) => (
                                    <SelectItem
                                        key={event.value}
                                        value={event.value}
                                    >
                                        {event.label}
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
                                    apply({ search: null, event: null });
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
                            {audits.total}{' '}
                            {audits.total === 1 ? 'event' : 'events'}
                        </CardTitle>
                        <CardDescription className="text-xs">
                            Page {audits.current_page} of {audits.last_page}
                        </CardDescription>
                    </CardHeader>

                    <CardContent className="px-0">
                        {audits.data.length === 0 ? (
                            <EmptyState
                                icon={ScrollText}
                                title="No authentication events"
                                description="Sign-ins are recorded here as soon as they happen."
                            />
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>When</TableHead>
                                        <TableHead>Account</TableHead>
                                        <TableHead>Event</TableHead>
                                        <TableHead>Device</TableHead>
                                        <TableHead>IP address</TableHead>
                                    </TableRow>
                                </TableHeader>

                                <TableBody>
                                    {audits.data.map((audit) => (
                                        <TableRow key={audit.id}>
                                            <TableCell className="text-xs whitespace-nowrap text-muted-foreground">
                                                {audit.created_at
                                                    ? new Date(
                                                          audit.created_at,
                                                      ).toLocaleString()
                                                    : '—'}
                                            </TableCell>

                                            <TableCell>
                                                <p className="font-medium">
                                                    {audit.user ??
                                                        audit.email ??
                                                        'Unknown'}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {audit.email}
                                                    {audit.client
                                                        ? ` · ${audit.client}`
                                                        : ''}
                                                </p>
                                            </TableCell>

                                            <TableCell>
                                                <StatusBadge
                                                    status={audit.event_tone}
                                                    label={audit.event_label}
                                                />
                                            </TableCell>

                                            <TableCell className="text-sm">
                                                {audit.device}
                                            </TableCell>

                                            <TableCell className="font-mono text-xs text-muted-foreground">
                                                {audit.ip_address ?? '—'}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>

                <Pagination paginator={audits} noun="events" />
            </div>
        </>
    );
}

AdminLoginAudit.layout = {
    breadcrumbs: [
        {
            title: 'Users',
            href: urls.admin.users.index,
        },
        {
            title: 'Login audit',
            href: urls.admin.loginAudit.index,
        },
    ],
};
