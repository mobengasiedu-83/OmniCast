import { Head, Link, router, useForm } from '@inertiajs/react';
import { Plus, Trash2, Workflow } from 'lucide-react';
import { type FormEvent } from 'react';
import { useConfirm } from '@/components/confirm-dialog';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { urls } from '@/lib/urls';
import type {
    AutomationLimits,
    AutomationNamedOption,
    AutomationRunRow,
    AutomationTriggerOption,
    AutomationWorkflowRow,
} from '@/types';

type PageProps = {
    workflows: AutomationWorkflowRow[];
    recentRuns: AutomationRunRow[];
    triggers: AutomationTriggerOption[];
    tags: AutomationNamedOption[];
    limits: AutomationLimits;
    enabled: boolean;
};

type FormData = {
    name: string;
    description: string;
    trigger: string;
    tag_id: string;
};

const emptyForm: FormData = {
    name: '',
    description: '',
    trigger: 'contact.created',
    tag_id: '',
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

export default function AutomationsIndex({
    workflows,
    recentRuns,
    triggers,
    tags,
    limits,
    enabled,
}: PageProps) {
    const confirm = useConfirm();
    const form = useForm<FormData>({ ...emptyForm });

    const selectedTrigger = triggers.find(
        (trigger) => trigger.value === form.data.trigger,
    );

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        form.post(urls.automations.store, {
            preserveScroll: true,
            onSuccess: () => form.setData({ ...emptyForm }),
        });
    };

    const toggle = (workflow: AutomationWorkflowRow): void => {
        router.patch(
            urls.automations.toggle(workflow.id),
            {},
            { preserveScroll: true },
        );
    };

    const remove = async (workflow: AutomationWorkflowRow): Promise<void> => {
        const confirmed = await confirm({
            title: `Delete ${workflow.name}?`,
            description:
                'The steps and the run history of this workflow are deleted with it.',
            confirmLabel: 'Delete workflow',
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        router.delete(urls.automations.destroy(workflow.id), {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Automations" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Automations"
                    description="A workflow is one trigger and an ordered list of steps, worked through by the queue for every contact it matches."
                />

                {!enabled && (
                    <Card className="border-warning/30 bg-warning-soft">
                        <CardHeader className="py-3">
                            <CardTitle className="text-sm font-medium">
                                Automations are switched off
                            </CardTitle>
                            <CardDescription className="text-xs">
                                Workflows are kept, but no run starts until the
                                platform turns automations back on.
                            </CardDescription>
                        </CardHeader>
                    </Card>
                )}

                <div className="grid gap-6 lg:grid-cols-[1fr_1.4fr]">
                    <Card>
                        <CardHeader className="border-b border-border/60 py-3">
                            <CardTitle className="flex items-center gap-2 text-sm font-medium">
                                <Plus className="size-4" />
                                New workflow
                            </CardTitle>
                            <CardDescription className="text-xs">
                                Up to {limits.max_steps} steps per workflow, and
                                a step may wait at most{' '}
                                {Math.round(limits.max_wait_minutes / 60)}{' '}
                                hours.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="pt-4">
                            <form onSubmit={submit} className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="workflow-name">Name</Label>
                                    <Input
                                        id="workflow-name"
                                        value={form.data.name}
                                        onChange={(event) =>
                                            form.setData(
                                                'name',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Welcome new contacts"
                                        required
                                    />
                                    {form.errors.name && (
                                        <p className="text-xs text-destructive">
                                            {form.errors.name}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="workflow-description">
                                        Description
                                    </Label>
                                    <Textarea
                                        id="workflow-description"
                                        rows={2}
                                        value={form.data.description}
                                        onChange={(event) =>
                                            form.setData(
                                                'description',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="What this workflow is for"
                                    />
                                    {form.errors.description && (
                                        <p className="text-xs text-destructive">
                                            {form.errors.description}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label>Trigger</Label>
                                    <Select
                                        value={form.data.trigger}
                                        onValueChange={(value) =>
                                            form.setData('trigger', value)
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {triggers.map((trigger) => (
                                                <SelectItem
                                                    key={trigger.value}
                                                    value={trigger.value}
                                                >
                                                    {trigger.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {selectedTrigger && (
                                        <p className="text-xs text-muted-foreground">
                                            {selectedTrigger.description}
                                        </p>
                                    )}
                                    {form.errors.trigger && (
                                        <p className="text-xs text-destructive">
                                            {form.errors.trigger}
                                        </p>
                                    )}
                                </div>

                                {form.data.trigger === 'contact.tagged' && (
                                    <div className="space-y-2">
                                        <Label>Tag</Label>
                                        <Select
                                            value={form.data.tag_id}
                                            onValueChange={(value) =>
                                                form.setData('tag_id', value)
                                            }
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Choose a tag" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {tags.map((tag) => (
                                                    <SelectItem
                                                        key={tag.id}
                                                        value={String(tag.id)}
                                                    >
                                                        {tag.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        {form.errors.tag_id && (
                                            <p className="text-xs text-destructive">
                                                {form.errors.tag_id}
                                            </p>
                                        )}
                                    </div>
                                )}

                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                    className="w-full"
                                >
                                    Create workflow
                                </Button>
                            </form>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="border-b border-border/60 py-3">
                            <CardTitle className="flex items-center gap-2 text-sm font-medium">
                                <Workflow className="size-4" />
                                {workflows.length} workflow
                                {workflows.length === 1 ? '' : 's'}
                            </CardTitle>
                            <CardDescription className="text-xs">
                                Open a workflow to add steps and read its runs.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3 pt-4">
                            {workflows.length === 0 && (
                                <p className="text-sm text-muted-foreground">
                                    No workflow yet. Create one on the left,
                                    then add the steps it should run.
                                </p>
                            )}

                            {workflows.map((workflow) => (
                                <div
                                    key={workflow.id}
                                    className="rounded-lg border border-border/60 p-3"
                                >
                                    <div className="flex flex-wrap items-start justify-between gap-2">
                                        <div className="space-y-1">
                                            <Link
                                                href={urls.automations.show(
                                                    workflow.id,
                                                )}
                                                className="text-sm font-medium hover:underline"
                                            >
                                                {workflow.name}
                                            </Link>
                                            <p className="text-xs text-muted-foreground">
                                                {workflow.trigger_summary}
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Badge
                                                variant={
                                                    workflow.is_active
                                                        ? 'success'
                                                        : 'muted'
                                                }
                                            >
                                                {workflow.is_active
                                                    ? 'Live'
                                                    : 'Paused'}
                                            </Badge>
                                            <Badge variant="secondary">
                                                {workflow.steps.length} step
                                                {workflow.steps.length === 1
                                                    ? ''
                                                    : 's'}
                                            </Badge>
                                        </div>
                                    </div>

                                    {workflow.description && (
                                        <p className="mt-2 text-xs text-muted-foreground">
                                            {workflow.description}
                                        </p>
                                    )}

                                    <div className="mt-3 flex flex-wrap items-center gap-2">
                                        <Button
                                            size="sm"
                                            variant="secondary"
                                            asChild
                                        >
                                            <Link
                                                href={urls.automations.show(
                                                    workflow.id,
                                                )}
                                            >
                                                Open
                                            </Link>
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="secondary"
                                            onClick={() => toggle(workflow)}
                                        >
                                            {workflow.is_active
                                                ? 'Pause'
                                                : 'Resume'}
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="destructive"
                                            onClick={() =>
                                                void remove(workflow)
                                            }
                                        >
                                            <Trash2 />
                                        </Button>
                                        <span className="text-xs text-muted-foreground">
                                            {workflow.runs_count} run
                                            {workflow.runs_count === 1
                                                ? ''
                                                : 's'}
                                            {workflow.open_runs_count > 0
                                                ? ` · ${workflow.open_runs_count} in flight`
                                                : ''}
                                        </span>
                                    </div>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader className="border-b border-border/60 py-3">
                        <CardTitle className="text-sm font-medium">
                            Latest runs
                        </CardTitle>
                        <CardDescription className="text-xs">
                            Every workflow you run writes history you can audit.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-2 pt-4">
                        {recentRuns.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                No run yet. A workflow starts the moment its
                                trigger fires.
                            </p>
                        )}

                        {recentRuns.map((run) => (
                            <div
                                key={run.id}
                                className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-border/60 px-3 py-2"
                            >
                                <div className="space-y-0.5">
                                    <Link
                                        href={urls.automations.runs.show(
                                            run.id,
                                        )}
                                        className="text-sm font-medium hover:underline"
                                    >
                                        {run.workflow.name ??
                                            'Deleted workflow'}
                                    </Link>
                                    <p className="text-xs text-muted-foreground">
                                        {run.contact?.name ?? 'No contact'} ·
                                        step {run.current_position}
                                        {run.detail ? ` · ${run.detail}` : ''}
                                    </p>
                                </div>
                                <Badge
                                    variant={
                                        statusVariant[run.status] ?? 'secondary'
                                    }
                                >
                                    {run.status_label}
                                </Badge>
                            </div>
                        ))}

                        <Button size="sm" variant="secondary" asChild>
                            <Link href={urls.automations.runs.index}>
                                All runs
                            </Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
