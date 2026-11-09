import { Head, Link, router } from '@inertiajs/react';
import { Bell, Check, CheckCheck, Trash2, Undo2 } from 'lucide-react';
import { useConfirm } from '@/components/confirm-dialog';
import EmptyState from '@/components/empty-state';
import PageHeader from '@/components/page-header';
import Pagination from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { urls } from '@/lib/urls';
import { cn } from '@/lib/utils';
import type {
    NotificationCategoryOption,
    NotificationRow,
    Paginated,
} from '@/types';

type PageProps = {
    notifications: Paginated<NotificationRow>;
    filters: { filter: 'all' | 'unread'; category: string | null };
    counts: { total: number; unread: number };
    categories: NotificationCategoryOption[];
};

const severityTone: Record<
    string,
    'info' | 'success' | 'warning' | 'destructive' | 'muted'
> = {
    info: 'info',
    success: 'success',
    warning: 'warning',
    critical: 'destructive',
};

const formatDateTime = (value: string | null): string =>
    value ? new Date(value).toLocaleString() : '—';

const filterHref = (
    filter: 'all' | 'unread',
    category: string | null,
): string => {
    const params = new URLSearchParams();

    if (filter === 'unread') {
        params.set('filter', 'unread');
    }

    if (category !== null) {
        params.set('category', category);
    }

    const query = params.toString();

    return query === ''
        ? urls.notifications.index
        : `${urls.notifications.index}?${query}`;
};

/**
 * The signed-in account's notification centre.
 *
 * The bell in the header reads the same rows; this screen is where they are
 * read, filtered, marked unread again and dismissed.
 */
export default function NotificationsIndex({
    notifications,
    filters,
    counts,
    categories,
}: PageProps) {
    const confirm = useConfirm();

    const clearRead = async (): Promise<void> => {
        const confirmed = await confirm({
            title: 'Clear every read notification?',
            description:
                'Only notifications you have already opened are removed. Unread notices stay in the inbox.',
            confirmLabel: 'Clear read',
            tone: 'danger',
        });

        if (confirmed) {
            router.delete(urls.notifications.clearRead, {
                preserveScroll: true,
            });
        }
    };

    return (
        <>
            <Head title="Notifications" />
            <PageHeader
                icon={Bell}
                title="Notifications"
                description="Everything the platform and your own account events want you to know about. Delivery of each category can be tuned on the notification settings screen."
                actions={
                    <div className="flex flex-wrap gap-2">
                        <Button
                            variant="secondary"
                            size="sm"
                            disabled={counts.unread === 0}
                            onClick={() =>
                                router.post(
                                    urls.notifications.readAll,
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <CheckCheck />
                            Mark all read
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={counts.total === counts.unread}
                            onClick={() => void clearRead()}
                        >
                            <Trash2 />
                            Clear read
                        </Button>
                        <Button asChild variant="ghost" size="sm">
                            <Link href={urls.account.notifications.index}>
                                Settings
                            </Link>
                        </Button>
                    </div>
                }
            />

            <div className="mt-6 flex flex-wrap items-center gap-2">
                {(
                    [
                        ['all', 'All', counts.total],
                        ['unread', 'Unread', counts.unread],
                    ] as const
                ).map(([value, label, count]) => (
                    <Button
                        key={value}
                        asChild
                        size="sm"
                        variant={filters.filter === value ? 'default' : 'outline'}
                    >
                        <Link href={filterHref(value, filters.category)}>
                            {label}
                            <span className="ml-1 text-xs opacity-70">
                                {count}
                            </span>
                        </Link>
                    </Button>
                ))}

                <span className="mx-1 hidden h-5 w-px bg-border sm:block" />

                {categories.map((category) => (
                    <Button
                        key={category.value}
                        asChild
                        size="sm"
                        variant={
                            filters.category === category.value
                                ? 'secondary'
                                : 'ghost'
                        }
                    >
                        <Link
                            href={filterHref(filters.filter, category.value)}
                        >
                            {category.label}
                        </Link>
                    </Button>
                ))}

                {filters.category !== null && (
                    <Button asChild size="sm" variant="ghost">
                        <Link href={filterHref(filters.filter, null)}>
                            Clear category
                        </Link>
                    </Button>
                )}
            </div>

            <Card className="mt-4">
                <CardContent className="pt-0">
                    {notifications.data.length === 0 ? (
                        <EmptyState
                            icon={Bell}
                            title="Nothing to read here"
                            description={
                                filters.filter === 'unread'
                                    ? 'Every notification has been read.'
                                    : 'Notifications from your campaigns, wallet and platform announcements will appear here.'
                            }
                        />
                    ) : (
                        <div className="divide-y divide-border/60">
                            {notifications.data.map((notification) => (
                                <article
                                    key={notification.id}
                                    className={cn(
                                        'flex flex-col gap-3 py-4 sm:flex-row sm:items-start sm:justify-between',
                                        notification.read_at === null &&
                                            'bg-brand-soft/20',
                                    )}
                                >
                                    <NotificationBody
                                        notification={notification}
                                    />
                                    <NotificationActions
                                        notification={notification}
                                    />
                                </article>
                            ))}
                        </div>
                    )}
                </CardContent>
            </Card>

            <div className="mt-4">
                <Pagination paginator={notifications} noun="notifications" />
            </div>
        </>
    );
}

/** The message itself, with the tone and the link the sender attached. */
function NotificationBody({ notification }: { notification: NotificationRow }) {
    return (
        <div className="min-w-0 space-y-1.5">
            <div className="flex flex-wrap items-center gap-2">
                <Badge variant={severityTone[notification.severity] ?? 'muted'}>
                    {notification.category_label}
                </Badge>
                {notification.read_at === null && (
                    <Badge variant="default">Unread</Badge>
                )}
                <span className="text-xs text-muted-foreground">
                    {formatDateTime(notification.created_at)}
                </span>
            </div>

            <p className="font-medium">{notification.title}</p>
            <p className="text-sm text-muted-foreground">
                {notification.body}
            </p>

            {notification.action_url !== null && (
                <Button asChild size="sm" variant="secondary">
                    <Link href={notification.action_url}>
                        {notification.action_label ?? 'Open'}
                    </Link>
                </Button>
            )}
        </div>
    );
}

/** Read/unread and delete, posted from the row they belong to. */
function NotificationActions({
    notification,
}: {
    notification: NotificationRow;
}) {
    return (
        <div className="flex shrink-0 items-center gap-1">
            {notification.read_at === null ? (
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={() =>
                        router.post(
                            urls.notifications.read(notification.id),
                            {},
                            { preserveScroll: true },
                        )
                    }
                >
                    <Check />
                    Read
                </Button>
            ) : (
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={() =>
                        router.post(
                            urls.notifications.unread(notification.id),
                            {},
                            { preserveScroll: true },
                        )
                    }
                >
                    <Undo2 />
                    Unread
                </Button>
            )}

            <Button
                variant="ghost"
                size="icon"
                aria-label="Delete notification"
                onClick={() =>
                    router.delete(urls.notifications.destroy(notification.id), {
                        preserveScroll: true,
                    })
                }
            >
                <Trash2 />
            </Button>
        </div>
    );
}
