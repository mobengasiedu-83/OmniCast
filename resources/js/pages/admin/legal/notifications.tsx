import { Head, router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import PageHeader from '@/components/page-header';
import Pagination from '@/components/pagination';
import StatCard from '@/components/stat-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
import type { Paginated, SelectOption } from '@/types';

type NotificationRow = {
    id: number;
    document: string | null;
    document_slug: string | null;
    version: string | null;
    account: string | null;
    email: string | null;
    client: string | null;
    client_code: string | null;
    sent_at: string | null;
    acknowledged_at: string | null;
};

type PageProps = {
    notifications: Paginated<NotificationRow>;
    filters: { document: string; state: string };
    documents: SelectOption[];
    stats: {
        total: number;
        last_30_days: number;
        acknowledged: number;
        pending: number;
    };
};

/**
 * Which accounts were told about which policy version, and who has since
 * acknowledged it.
 *
 * The rows are written when an administrator announces a version; the
 * acknowledgement date is stamped by {@see LegalLibrary::recordAcceptance()} the
 * moment the account accepts that version anywhere on the platform.
 */
export default function PolicyNotifications({
    notifications,
    filters,
    documents,
    stats,
}: PageProps) {
    const [document, setDocument] = useState(filters.document);
    const [state, setState] = useState(filters.state);

    const apply = (event: FormEvent): void => {
        event.preventDefault();
        router.get(
            urls.admin.legal.notifications,
            { document: document || null, state: state || null },
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Policy notifications" />

            <div className="grid gap-6">
                <PageHeader
                    title="Policy notifications"
                    description="Every account told about a policy change, and whether it has acknowledged the new version."
                />

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard title="Announcements" value={stats.total} />
                    <StatCard title="Last 30 days" value={stats.last_30_days} />
                    <StatCard title="Acknowledged" value={stats.acknowledged} />
                    <StatCard title="Awaiting" value={stats.pending} />
                </div>

                <Card>
                    <CardContent className="grid gap-4">
                        <form
                            className="flex flex-wrap items-end gap-2"
                            onSubmit={apply}
                        >
                            <Select
                                value={document || 'all'}
                                onValueChange={(value) =>
                                    setDocument(value === 'all' ? '' : value)
                                }
                            >
                                <SelectTrigger className="w-64">
                                    <SelectValue placeholder="All documents" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All documents
                                    </SelectItem>
                                    {documents.map((option) => (
                                        <SelectItem
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>

                            <Select
                                value={state || 'all'}
                                onValueChange={(value) =>
                                    setState(value === 'all' ? '' : value)
                                }
                            >
                                <SelectTrigger className="w-48">
                                    <SelectValue placeholder="Any state" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        Any state
                                    </SelectItem>
                                    <SelectItem value="pending">
                                        Awaiting acknowledgement
                                    </SelectItem>
                                    <SelectItem value="acknowledged">
                                        Acknowledged
                                    </SelectItem>
                                </SelectContent>
                            </Select>

                            <Button type="submit" variant="outline">
                                Filter
                            </Button>
                        </form>

                        <NotificationTable rows={notifications} />

                        <Pagination
                            paginator={notifications}
                            noun="announcements"
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

PolicyNotifications.layout = {
    breadcrumbs: [
        {
            title: 'Policy notifications',
            href: urls.admin.legal.notifications,
        },
    ],
};

/**
 * One page of announcements.
 */
function NotificationTable({ rows }: { rows: Paginated<NotificationRow> }) {
    return (
        <>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Document</TableHead>
                        <TableHead>Version</TableHead>
                        <TableHead>Organisation</TableHead>
                        <TableHead>Account</TableHead>
                        <TableHead>Sent</TableHead>
                        <TableHead>Acknowledged</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {rows.data.map((notification) => (
                        <TableRow key={notification.id}>
                            <TableCell className="font-medium">
                                {notification.document}
                                {notification.document_slug && (
                                    <p className="text-xs text-muted-foreground">
                                        /legal/{notification.document_slug}
                                    </p>
                                )}
                            </TableCell>
                            <TableCell>{notification.version ?? '—'}</TableCell>
                            <TableCell>
                                {notification.client ?? '—'}
                                {notification.client_code && (
                                    <p className="text-xs text-muted-foreground">
                                        {notification.client_code}
                                    </p>
                                )}
                            </TableCell>
                            <TableCell>
                                {notification.account ?? '—'}
                                <p className="text-xs text-muted-foreground">
                                    {notification.email ?? '—'}
                                </p>
                            </TableCell>
                            <TableCell className="text-xs whitespace-nowrap text-muted-foreground">
                                {notification.sent_at
                                    ? new Date(
                                          notification.sent_at,
                                      ).toLocaleString()
                                    : '—'}
                            </TableCell>
                            <TableCell>
                                {notification.acknowledged_at ? (
                                    <span className="text-xs text-muted-foreground">
                                        {new Date(
                                            notification.acknowledged_at,
                                        ).toLocaleDateString()}
                                    </span>
                                ) : (
                                    <Badge variant="warning">Awaiting</Badge>
                                )}
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>

            {rows.data.length === 0 && (
                <p className="p-8 text-center text-sm text-muted-foreground">
                    No policy has been announced yet. Announce a published
                    version from Legal documents to notify every client account
                    that a policy changed.
                </p>
            )}
        </>
    );
}
