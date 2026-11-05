import { Head } from '@inertiajs/react';
import { Gauge, Layers, Radio, Timer, TriangleAlert } from 'lucide-react';
import PageHeader from '@/components/page-header';
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
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { urls } from '@/lib/urls';
import type { DeliveryChannelRow, DeliveryQueueRow } from '@/types';

type PageProps = {
    connection: {
        name: string;
        driver: string;
        horizon: { path: string; accessible: boolean };
        scheduler: {
            stalled_after_minutes: number;
            failed_prune_after_hours: number;
        };
    };
    queues: DeliveryQueueRow[];
    channels: DeliveryChannelRow[];
    totals: { queued: number; failed: number };
};

/**
 * The delivery topology: which queue each channel drains, how much work is
 * waiting on it and which retry policy the workers apply.
 */
export default function AdminQueues({
    connection,
    queues,
    channels,
    totals,
}: PageProps) {
    return (
        <>
            <Head title="Delivery queues" />

            <div className="flex flex-col gap-4">
                <PageHeader
                    icon={Gauge}
                    title="Delivery queues"
                    description="Every queue the dispatcher releases work onto, with the worker pool, throughput ceiling and retry policy that owns it."
                    badge={
                        <StatusBadge
                            status={totals.failed > 0 ? 'failed' : 'running'}
                            label={`${connection.driver} · ${connection.name}`}
                        />
                    }
                    actions={
                        connection.horizon.accessible ? (
                            <Button asChild variant="outline">
                                <a
                                    href={connection.horizon.path}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    Open Horizon
                                </a>
                            </Button>
                        ) : null
                    }
                />

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        title="Waiting jobs"
                        value={totals.queued}
                        description="Queued across every delivery queue"
                        icon={Layers}
                        tone="brand"
                    />
                    <StatCard
                        title="Failed jobs"
                        value={totals.failed}
                        description={`Pruned after ${connection.scheduler.failed_prune_after_hours}h`}
                        icon={TriangleAlert}
                        tone={totals.failed > 0 ? 'danger' : 'success'}
                    />
                    <StatCard
                        title="Active channels"
                        value={channels.length}
                        description="SMS, voice and email submitters"
                        icon={Radio}
                        tone="signal"
                    />
                    <StatCard
                        title="Stalled after"
                        value={`${connection.scheduler.stalled_after_minutes}m`}
                        description="Quiet running campaigns are re-queued"
                        icon={Timer}
                        tone="neutral"
                    />
                </div>

                <Card className="overflow-hidden">
                    <CardHeader className="border-b border-border/60 py-3">
                        <CardTitle className="text-sm font-medium">
                            Queue topology
                        </CardTitle>
                        <CardDescription className="text-xs">
                            Multi-channel campaigns orchestrate on{' '}
                            <code className="font-mono">campaigns</code>;
                            single-channel campaigns and their retries stay on
                            their channel queue so a slow provider never starves
                            the others.
                        </CardDescription>
                    </CardHeader>

                    <CardContent className="px-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Queue</TableHead>
                                    <TableHead>Supervisor</TableHead>
                                    <TableHead className="text-right">
                                        Waiting
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Failed
                                    </TableHead>
                                    <TableHead>Retry policy</TableHead>
                                    <TableHead className="text-right">
                                        Ceiling
                                    </TableHead>
                                </TableRow>
                            </TableHeader>

                            <TableBody>
                                {queues.map((queue) => (
                                    <TableRow key={queue.name}>
                                        <TableCell>
                                            <div className="font-mono text-xs font-medium">
                                                {queue.name}
                                            </div>
                                            <div className="text-muted-foreground mt-1 max-w-sm text-xs">
                                                {queue.purpose}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            {queue.supervisor ? (
                                                <Badge variant="secondary">
                                                    {queue.supervisor}
                                                </Badge>
                                            ) : (
                                                <span className="text-muted-foreground text-xs">
                                                    Unsupervised
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {queue.queued}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {queue.failed > 0 ? (
                                                <span className="text-destructive font-medium">
                                                    {queue.failed}
                                                </span>
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    0
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground text-xs">
                                            {queue.tries} tries · backoff{' '}
                                            {queue.backoff} · timeout{' '}
                                            {queue.timeout}
                                        </TableCell>
                                        <TableCell className="text-right text-xs">
                                            {queue.channels.length === 0 ? (
                                                <span className="text-muted-foreground">
                                                    —
                                                </span>
                                            ) : queue.cps > 0 ? (
                                                `${queue.cps}/s`
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    Unthrottled
                                                </span>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader className="border-b border-border/60 py-3">
                            <CardTitle className="text-sm font-medium">
                                Throughput ceilings
                            </CardTitle>
                            <CardDescription className="text-xs">
                                The dispatcher sleeps between submissions once a
                                channel reaches its per-second budget. A client
                                override wins over the platform setting, which
                                wins over the configured default; zero means the
                                channel is unthrottled.
                            </CardDescription>
                        </CardHeader>

                        <CardContent className="px-0">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Channel</TableHead>
                                        <TableHead>Queue</TableHead>
                                        <TableHead className="text-right">
                                            Messages / second
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>

                                <TableBody>
                                    {channels.map((channel) => (
                                        <TableRow key={channel.value}>
                                            <TableCell className="font-medium">
                                                {channel.label}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground font-mono text-xs">
                                                {channel.queue}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {channel.cps > 0 ? (
                                                    channel.cps
                                                ) : (
                                                    <span className="text-muted-foreground">
                                                        Unthrottled
                                                    </span>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="border-b border-border/60 py-3">
                            <CardTitle className="text-sm font-medium">
                                Running the engine
                            </CardTitle>
                            <CardDescription className="text-xs">
                                Horizon drains the queues and the scheduler is
                                the heartbeat that releases campaigns and
                                rescues them, so both have to run for delivery to
                                progress.
                            </CardDescription>
                        </CardHeader>

                        <CardContent className="space-y-3 pt-4">
                            <Command
                                command="php artisan horizon"
                                description="Start the supervised workers for every queue."
                            />
                            <Command
                                command="php artisan schedule:work"
                                description="Release scheduled campaigns every minute and sweep stalled ones every five."
                            />
                            <Command
                                command="php artisan campaigns:sweep --limit=10"
                                description="Re-queue stalled campaigns; the sweep runs every five minutes on its own."
                            />
                            <Command
                                command={`php artisan queue:prune-failed --hours=${connection.scheduler.failed_prune_after_hours}`}
                                description="Drop failed jobs past their retention window; the scheduler runs this nightly."
                            />
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

/**
 * One copyable command with the sentence that says when to reach for it.
 */
function Command({
    command,
    description,
}: {
    command: string;
    description: string;
}) {
    return (
        <div className="bg-muted/40 rounded-lg border border-border/60 p-2.5">
            <code className="font-mono text-[0.7rem] break-all">{command}</code>
            <p className="text-muted-foreground mt-1 text-xs">{description}</p>
        </div>
    );
}

AdminQueues.layout = {
    breadcrumbs: [
        {
            title: 'Delivery queues',
            href: urls.admin.queues.index,
        },
    ],
};

