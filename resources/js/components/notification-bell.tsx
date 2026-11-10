import { Link, router, usePage, usePoll } from '@inertiajs/react';
import { Bell, CheckCheck } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import type { NotificationCentre } from '@/types';

/**
 * The unread badge in the header.
 *
 * The unread count and newest rows arrive as a shared prop. The bell refreshes
 * that prop every 15 seconds so new notices appear without a page navigation.
 */
export function NotificationBell() {
    const { notificationCentre } = usePage<{
        notificationCentre: NotificationCentre | null;
    }>().props;

    return notificationCentre === null ? null : (
        <NotificationBellContent notificationCentre={notificationCentre} />
    );
}

function NotificationBellContent({
    notificationCentre,
}: {
    notificationCentre: NotificationCentre;
}) {
    usePoll(15_000, { only: ['notificationCentre'] });

    const { unread_count: unreadCount, items } = notificationCentre;

    const markAllRead = (): void => {
        router.post(
            notificationCentre.read_all_url,
            {},
            { preserveScroll: true, preserveState: true },
        );
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="relative"
                    aria-label={
                        unreadCount === 0
                            ? 'Notifications'
                            : `Notifications, ${unreadCount} unread`
                    }
                >
                    <Bell className="size-5" />
                    {unreadCount > 0 && (
                        <span className="absolute -top-0.5 -right-0.5 flex min-w-4 items-center justify-center rounded-full brand-gradient px-1 text-[10px] leading-4 font-semibold text-primary-foreground">
                            {unreadCount > 9 ? '9+' : unreadCount}
                        </span>
                    )}
                </Button>
            </DropdownMenuTrigger>

            <DropdownMenuContent align="end" className="w-80">
                <div className="flex items-center justify-between px-2 py-1.5">
                    <p className="text-sm font-semibold">Notifications</p>
                    {unreadCount > 0 && (
                        <button
                            type="button"
                            onClick={markAllRead}
                            className="flex items-center gap-1 text-xs font-medium text-brand hover:underline"
                        >
                            <CheckCheck className="size-3.5" />
                            Mark all read
                        </button>
                    )}
                </div>

                <DropdownMenuSeparator />

                {items.length === 0 ? (
                    <p className="px-2 py-6 text-center text-sm text-muted-foreground">
                        You are all caught up.
                    </p>
                ) : (
                    items.map((item) => (
                        <DropdownMenuItem key={item.id} asChild>
                            <Link
                                href={notificationCentre.index_url}
                                className="flex flex-col items-start gap-0.5"
                            >
                                <span
                                    className={cn(
                                        'text-sm font-medium',
                                        item.severity === 'critical' &&
                                            'text-destructive',
                                    )}
                                >
                                    {item.title}
                                </span>
                                <span className="line-clamp-2 text-xs text-muted-foreground">
                                    {item.body}
                                </span>
                            </Link>
                        </DropdownMenuItem>
                    ))
                )}

                <DropdownMenuSeparator />

                <DropdownMenuItem asChild>
                    <Link
                        href={notificationCentre.index_url}
                        className="justify-center text-sm font-medium"
                    >
                        Open the notification centre
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
