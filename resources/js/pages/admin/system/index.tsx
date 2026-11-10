import { Head, Link, usePoll } from '@inertiajs/react';
import {
    Activity,
    ArrowUpRight,
    CircleAlert,
    Database,
    HardDrive,
    Layers,
    ServerCog,
    ShieldCheck,
} from 'lucide-react';
import PageHeader from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { urls } from '@/lib/urls';
import systemRoutes from '@/routes/admin/system';

type HealthCheck = {
    key: string;
    label: string;
    status: 'ok' | 'failed' | 'not_applicable';
    message: string;
    metrics: Record<string, unknown> | null;
};

type PageProps = {
    health: {
        status: 'ok' | 'unavailable';
        checked_at: string;
        checks: HealthCheck[];
    };
    runtime: {
        environment: string;
        php: string;
        laravel: string;
        queue_connection: string;
        queue_driver: string;
        cache_store: string;
        maintenance: boolean;
        horizon_path: string;
        horizon_accessible: boolean;
        support_accessible: boolean;
    };
};

const checkIcons: Record<string, typeof Database> = {
    database: Database,
    cache: Layers,
    storage: HardDrive,
    queue: Activity,
    horizon: ServerCog,
};

function checkedAt(value: string): string {
    return new Date(value).toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'medium',
    });
}

/**
 * Platform-only view of application dependencies and delivery operations.
 */
export default function AdminSystem({ health, runtime }: PageProps) {
    usePoll(30_000, { only: ['health'] });

    const queueCheck = health.checks.find((check) => check.key === 'queue');
    const queueDepths = Object.entries(queueCheck?.metrics ?? {}).filter(
        (entry): entry is [string, number] => typeof entry[1] === 'number',
    );

    return (
        <>
            <Head title="System health" />

            <div className="flex flex-col gap-4">
                <PageHeader
                    icon={ServerCog}
                    title="System health"
                    description="Application dependencies, worker health, and operational recovery guidance."
                    badge={
                        <Badge
                            variant={
                                health.status === 'ok'
                                    ? 'secondary'
                                    : 'destructive'
                            }
                        >
                            {health.status === 'ok'
                                ? 'All checks healthy'
                                : 'Action required'}
                        </Badge>
                    }
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <Button asChild variant="outline">
                                <Link href={urls.admin.queues.index}>
                                    Delivery queues
                                    <ArrowUpRight />
                                </Link>
                            </Button>
                            {runtime.support_accessible && (
                                <Button asChild variant="outline">
                                    <Link href={urls.admin.support.tickets.index}>
                                        Support tickets
                                        <ArrowUpRight />
                                    </Link>
                                </Button>
                            )}
                        </div>
                    }
                />

                <p className="text-xs text-muted-foreground">
                    Last checked {checkedAt(health.checked_at)} · refreshes
                    every 30 seconds
                </p>

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Runtime</CardDescription>
                            <CardTitle className="text-base">
                                {runtime.environment}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="text-sm text-muted-foreground">
                            PHP {runtime.php} · Laravel {runtime.laravel}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Queue backend</CardDescription>
                            <CardTitle className="text-base">
                                {runtime.queue_driver} · {runtime.queue_connection}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="text-sm text-muted-foreground">
                            Cache store: {runtime.cache_store}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Maintenance mode</CardDescription>
                            <CardTitle className="text-base">
                                {runtime.maintenance ? 'Enabled' : 'Disabled'}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="text-sm text-muted-foreground">
                            {runtime.maintenance
                                ? 'The application is currently unavailable to visitors.'
                                : 'The application is accepting requests.'}
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader className="border-b border-border/60 py-3">
                        <CardTitle className="flex items-center gap-2 text-sm font-medium">
                            <ShieldCheck className="size-4" />
                            Dependency checks
                        </CardTitle>
                        <CardDescription className="text-xs">
                            Checks are read-only apart from short-lived cache
                            and private-storage probe values, which are removed
                            immediately.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="divide-y divide-border/60 p-0">
                        {health.checks.map((check) => {
                            const Icon = checkIcons[check.key] ?? CircleAlert;

                            return (
                                <div
                                    key={check.key}
                                    className="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div className="flex items-start gap-3">
                                        <Icon className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                                        <div>
                                            <p className="text-sm font-medium">
                                                {check.label}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {check.message}
                                            </p>
                                        </div>
                                    </div>
                                    <Badge
                                        variant={
                                            check.status === 'ok'
                                                ? 'secondary'
                                                : check.status === 'failed'
                                                  ? 'destructive'
                                                  : 'outline'
                                        }
                                    >
                                        {check.status === 'not_applicable'
                                            ? 'Not applicable'
                                            : check.status === 'ok'
                                              ? 'Healthy'
                                              : 'Unavailable'}
                                    </Badge>
                                </div>
                            );
                        })}
                    </CardContent>
                </Card>

                {queueDepths.length > 0 && (
                    <Card>
                        <CardHeader className="border-b border-border/60 py-3">
                            <CardTitle className="text-sm font-medium">
                                Queue depth snapshot
                            </CardTitle>
                            <CardDescription className="text-xs">
                                Current waiting jobs by queue. Open delivery
                                queues for failed counts, retry policy and
                                supervisor details.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-wrap gap-2 pt-4">
                            {queueDepths.map(([name, count]) => (
                                <Badge
                                    key={name}
                                    variant="outline"
                                    className="font-mono"
                                >
                                    {name}: {count}
                                </Badge>
                            ))}
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader className="border-b border-border/60 py-3">
                        <CardTitle className="text-sm font-medium">
                            Operations runbook
                        </CardTitle>
                        <CardDescription className="text-xs">
                            Start with the readiness response and the
                            queue-specific dashboard; avoid restarting or
                            replaying work until the underlying cause is known.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4 pt-4 md:grid-cols-2">
                        <div className="space-y-2">
                            <h3 className="text-sm font-medium">
                                Readiness probe
                            </h3>
                            <p className="text-xs text-muted-foreground">
                                Configure the load balancer or monitor to check
                                <code className="mx-1 rounded bg-muted px-1 py-0.5">
                                    /api/v1/health/ready
                                </code>
                                . It returns 503 when a required dependency
                                cannot be verified; diagnostic details remain
                                behind staff authentication.
                            </p>
                        </div>
                        <div className="space-y-2">
                            <h3 className="text-sm font-medium">
                                Workers and scheduler
                            </h3>
                            <p className="text-xs text-muted-foreground">
                                Use Horizon to inspect supervisors and failed
                                jobs. The scheduler must run every minute to
                                release scheduled campaigns and recover stalled
                                work.
                            </p>
                            {runtime.horizon_accessible && (
                                <Button asChild size="sm" variant="outline">
                                    <a
                                        href={runtime.horizon_path}
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        Open Horizon
                                        <ArrowUpRight />
                                    </a>
                                </Button>
                            )}
                        </div>
                        <div className="space-y-2">
                            <h3 className="text-sm font-medium">
                                Safe recovery
                            </h3>
                            <p className="text-xs text-muted-foreground">
                                Inspect logs and failed-job payloads, correct
                                configuration or provider faults, then retry
                                only the affected jobs. Do not start a second
                                Horizon master or run a second scheduler beside
                                production supervision.
                            </p>
                        </div>
                        <div className="space-y-2">
                            <h3 className="text-sm font-medium">
                                Detailed procedures
                            </h3>
                            <p className="text-xs text-muted-foreground">
                                The operator runbook documents production
                                commands, queue diagnostics, deployment
                                ownership, and recovery cautions.
                            </p>
                            <Button asChild size="sm" variant="outline">
                                <a href={systemRoutes.runbook.url()}>
                                    Download the operations runbook
                                    <ArrowUpRight />
                                </a>
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AdminSystem.layout = {
    breadcrumbs: [
        {
            title: 'System health',
            href: systemRoutes.index.url(),
        },
    ],
};
