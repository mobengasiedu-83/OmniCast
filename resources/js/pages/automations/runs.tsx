import { Head, Link, router } from '@inertiajs/react';
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
import type { AutomationRunRow } from '@/types';

type PageProps = {
    runs: AutomationRunRow[];
    filters: { workflow: number | null; status: string | null };
    workflows: { id: number; name: string }[];
    statuses: { value: string; label: string }[];
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

const formatDate = (value: string | null): string =>
    value === null
        ? '—'
        : new Date(value).toLocaleString(undefined, {
              dateStyle: 'medium',
              timeStyle: 'short',
          });

export default function AutomationRuns({
    runs,
    filters,
    workflows,
    statuses,
    enabled,
}: PageProps) {
    const apply = (changes: Record<string, string | null>): void => {
        const query: Record<string, string> = {};

        const workflow = changes.workflow ?? filters.workflow?.toString() ?? '';
        const status = changes.status ?? filters.status ?? '';

        if (workflow !== '' && workflow !== 'all') {
            query.workflow = workflow;
        }

        if (status !== '' && status !== 'all') {
            query.status = status;
        }

        router.get(urls.automations.runs.index, query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    return (
        <>
            <Head title="Automation runs" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Automation runs"
                    description="Every contact a workflow worked through, newest first."
                />

                {!enabled && (
                    <Card className="border-warning/30 bg-warning-soft">
                        <CardHeader className="py-3">
                            <CardTitle className="text-sm font-medium">
                                Automations are switched off
                            </CardTitle>
                            <CardDescription className="text-xs">
                                The history below is read-only until the
                                platform turns automations back on.
                            </CardDescription>
                        </CardHeader>
                    </Card>
                )}

                <Card>
                    <CardHeader className="border-b border-border/60 py-3">
                        <CardTitle className="text-sm font-medium">
                            History
                        </CardTitle>
                        <CardDescription className="text-xs">
                            The newest 50 runs; finished runs are pruned after
                            the retention window.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4 pt-4">
                        <div className="flex flex-wrap items-center gap-2">
                            <Select
                                value={filters.workflow?.toString() ?? 'all'}
                                onValueChange={(value) =>
                                    apply({ workflow: value })
                                }
                            >
                                <SelectTrigger className="w-56">
                                    <SelectValue placeholder="All workflows" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All workflows
                                    </SelectItem>
                                    {workflows.map((workflow) => (
                                        <SelectItem
                                            key={workflow.id}
                                            value={String(workflow.id)}
                                        >
                                            {workflow.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>

                            <Select
                                value={filters.status ?? 'all'}
                                onValueChange={(value) =>
                                    apply({ status: value })
                                }
                            >
                                <SelectTrigger className="w-44">
                                    <SelectValue placeholder="Any status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        Any status
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

                            <Button size="sm" variant="secondary" asChild>
                                <Link href={urls.automations.index}>
                                    <ArrowLeft />
                                    Workflows
                                </Link>
                            </Button>
                        </div>

                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Workflow</TableHead>
                                    <TableHead>Contact</TableHead>
                                    <TableHead>Step</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Started</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {runs.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={5}
                                            className="text-sm text-muted-foreground"
                                        >
                                            No run matches this filter yet.
                                        </TableCell>
                                    </TableRow>
                                )}

                                {runs.map((run) => (
                                    <TableRow key={run.id}>
                                        <TableCell>
                                            <Link
                                                href={urls.automations.runs.show(
                                                    run.id,
                                                )}
                                                className="font-medium hover:underline"
                                            >
                                                {run.workflow.name ??
                                                    'Deleted workflow'}
                                            </Link>
                                            <p className="text-xs text-muted-foreground">
                                                {run.detail ??
                                                    run.trigger_label}
                                            </p>
                                        </TableCell>
                                        <TableCell className="text-sm">
                                            {run.contact_name ?? 'No contact'}
                                        </TableCell>
                                        <TableCell className="text-sm">
                                            {run.current_position}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant={
                                                    statusVariant[run.status] ??
                                                    'secondary'
                                                }
                                            >
                                                {run.status_label}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-xs text-muted-foreground">
                                            {formatDate(run.created_at)}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
