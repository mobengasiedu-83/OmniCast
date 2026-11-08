import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import Heading from '@/components/heading';
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
import type { AutomationRunDetail } from '@/types';

type PageProps = {
    run: AutomationRunDetail;
    enabled: boolean;
};

const statusVariant: Record<
    string,
    'success' | 'destructive' | 'warning' | 'muted' | 'info'
> = {
    completed: 'success',
    failed: 'destructive',
    running: 'info',
    waiting: 'warning',
    queued: 'muted',
    cancelled: 'muted',
};

const stepVariant: Record<string, 'success' | 'destructive' | 'muted'> = {
    completed: 'success',
    failed: 'destructive',
    skipped: 'muted',
};

const formatDate = (value: string | null): string =>
    value === null
        ? '—'
        : new Date(value).toLocaleString(undefined, {
              dateStyle: 'medium',
              timeStyle: 'short',
          });

export default function AutomationRun({ run }: PageProps) {
    return (
        <>
            <Head title={`Run #${run.id}`} />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={`Run #${run.id}`}
                    description={
                        run.workflow.name === null
                            ? 'The workflow this run belonged to was deleted.'
                            : `${run.workflow.name} · ${run.trigger_label}`
                    }
                />

                <div className="flex flex-wrap items-center gap-2">
                    <Badge variant={statusVariant[run.status] ?? 'secondary'}>
                        {run.status_label}
                    </Badge>
                    <Badge variant="secondary">
                        Step {run.current_position}
                    </Badge>
                    {run.workflow.id !== null && (
                        <Button size="sm" variant="secondary" asChild>
                            <Link href={urls.automations.show(run.workflow.id)}>
                                Open workflow
                            </Link>
                        </Button>
                    )}
                    <Button size="sm" variant="secondary" asChild>
                        <Link href={urls.automations.runs.index}>
                            <ArrowLeft />
                            All runs
                        </Link>
                    </Button>

                    <div className="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
                        <Card>
                            <CardHeader className="border-b border-border/60 py-3">
                                <CardTitle className="text-sm font-medium">
                                    Timeline
                                </CardTitle>
                                <CardDescription className="text-xs">
                                    What the engine did at every position it
                                    reached.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-2 pt-4">
                                {run.steps.length === 0 && (
                                    <p className="text-sm text-muted-foreground">
                                        No step has run yet. A queued run is
                                        picked up by the automation worker.
                                    </p>
                                )}

                                {run.steps.map((step) => (
                                    <div
                                        key={step.id}
                                        className="rounded-lg border border-border/60 px-3 py-2"
                                    >
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <p className="text-sm font-medium">
                                                {step.position}.{' '}
                                                {step.action_label}
                                            </p>
                                            <Badge
                                                variant={
                                                    stepVariant[step.status] ??
                                                    'secondary'
                                                }
                                            >
                                                {step.status_label}
                                            </Badge>
                                        </div>
                                        {step.detail && (
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {step.detail}
                                            </p>
                                        )}
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {formatDate(step.ran_at)}
                                        </p>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                    </div>

                    <Card>
                        <CardHeader className="border-b border-border/60 py-3">
                            <CardTitle className="text-sm font-medium">
                                Details
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3 pt-4 text-sm">
                            <div className="flex justify-between gap-2">
                                <span className="text-muted-foreground">
                                    Contact
                                </span>
                                <span>{run.contact?.name ?? 'None'}</span>
                            </div>
                            <div className="flex justify-between gap-2">
                                <span className="text-muted-foreground">
                                    Phone
                                </span>
                                <span>{run.contact?.phone ?? '—'}</span>
                            </div>
                            <div className="flex justify-between gap-2">
                                <span className="text-muted-foreground">
                                    Email
                                </span>
                                <span>{run.contact?.email ?? '—'}</span>
                            </div>
                            <div className="flex justify-between gap-2">
                                <span className="text-muted-foreground">
                                    Campaign
                                </span>
                                <span>{run.campaign?.name ?? '—'}</span>
                            </div>
                            <div className="flex justify-between gap-2">
                                <span className="text-muted-foreground">
                                    Queued
                                </span>
                                <span>{formatDate(run.created_at)}</span>
                            </div>
                            <div className="flex justify-between gap-2">
                                <span className="text-muted-foreground">
                                    Started
                                </span>
                                <span>{formatDate(run.started_at)}</span>
                            </div>
                            <div className="flex justify-between gap-2">
                                <span className="text-muted-foreground">
                                    Resumes
                                </span>
                                <span>{formatDate(run.resume_at)}</span>
                            </div>
                            <div className="flex justify-between gap-2">
                                <span className="text-muted-foreground">
                                    Finished
                                </span>
                                <span>{formatDate(run.finished_at)}</span>
                            </div>
                            {run.detail && (
                                <p className="rounded-lg border border-border/60 p-2 text-xs text-muted-foreground">
                                    {run.detail}
                                </p>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}
