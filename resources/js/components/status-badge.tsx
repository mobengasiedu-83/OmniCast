import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

type BadgeVariant =
    | 'default'
    | 'secondary'
    | 'destructive'
    | 'outline'
    | 'success'
    | 'warning'
    | 'info'
    | 'muted';

/**
 * The badge tone used for a campaign, contact, sender ID or ledger status.
 *
 * Green means the record is settled or live, amber means it is waiting on a
 * human, red means it stopped, grey means it never started.
 */
const tones: Record<string, BadgeVariant> = {
    active: 'success',
    approved: 'success',
    completed: 'success',
    delivered: 'success',
    sent: 'success',
    subscribed: 'success',
    paid: 'success',
    running: 'info',
    held: 'warning',
    pending: 'warning',
    pending_approval: 'warning',
    queued: 'warning',
    scheduled: 'warning',
    paused: 'warning',
    draft: 'muted',
    skipped: 'muted',
    unknown: 'muted',
    unsubscribed: 'muted',
    abandoned: 'muted',
    expired: 'muted',
    void: 'muted',
    cancelled: 'destructive',
    failed: 'destructive',
    rejected: 'destructive',
    refunded: 'info',
};

/** Statuses that are actively moving data and get a pulse on their dot. */
const liveStatuses = new Set(['active', 'running']);

export default function StatusBadge({
    status,
    label,
    className,
}: {
    status: string;
    label?: string;
    className?: string;
}) {
    return (
        <Badge
            variant={tones[status] ?? 'outline'}
            className={cn('capitalize', className)}
        >
            <span className="relative flex size-1.5 items-center justify-center">
                {liveStatuses.has(status) && (
                    <span className="animate-ping-soft absolute inline-flex size-1.5 rounded-full bg-current" />
                )}
                <span className="relative inline-flex size-1.5 rounded-full bg-current" />
            </span>
            {label ?? status.replace(/_/g, ' ')}
        </Badge>
    );
}

