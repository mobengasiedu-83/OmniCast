import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Plus, Trash2 } from 'lucide-react';
import { useState, type FormEvent } from 'react';
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
    AutomationActionOption,
    AutomationConditionOption,
    AutomationLimits,
    AutomationNamedOption,
    AutomationRunRow,
    AutomationStepRow,
    AutomationTriggerOption,
    AutomationWorkflowRow,
} from '@/types';

type PageProps = {
    workflow: AutomationWorkflowRow;
    runs: AutomationRunRow[];
    triggers: AutomationTriggerOption[];
    actions: AutomationActionOption[];
    conditions: AutomationConditionOption[];
    tags: AutomationNamedOption[];
    contactLists: AutomationNamedOption[];
    limits: AutomationLimits;
    enabled: boolean;
};

type WorkflowFormData = {
    name: string;
    description: string;
    trigger: string;
    tag_id: string;
    hour: string;
    is_active: boolean;
};

type StepFormData = {
    action: string;
    label: string;
    body: string;
    subject: string;
    from: string;
    tag_id: string;
    contact_list_id: string;
    check: string;
    field: string;
    value: string;
    minutes: string;
    url: string;
    secret: string;
    on_false_position: string;
};

const emptyStep: StepFormData = {
    action: 'send_sms',
    label: '',
    body: '',
    subject: '',
    from: '',
    tag_id: '',
    contact_list_id: '',
    check: 'has_tag',
    field: '',
    value: '',
    minutes: '60',
    url: '',
    secret: '',
    on_false_position: '',
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

/**
 * A stored configuration value, read back as the string an input shows.
 */
const text = (value: unknown): string => {
    if (typeof value === 'string') {
        return value;
    }

    return typeof value === 'number' || typeof value === 'boolean'
        ? String(value)
        : '';
};

export default function AutomationsShow({
    workflow,
    runs,
    triggers,
    actions,
    conditions,
    tags,
    contactLists,
    limits,
    enabled,
}: PageProps) {
    const confirm = useConfirm();
    const [editingStep, setEditingStep] = useState<AutomationStepRow | null>(
        null,
    );

    const workflowForm = useForm<WorkflowFormData>({
        name: workflow.name,
        description: workflow.description ?? '',
        trigger: workflow.trigger,
        tag_id: text(workflow.trigger_config?.tag_id),
        hour: text(workflow.trigger_config?.hour ?? 9),
        is_active: workflow.is_active,
    });

    const stepForm = useForm<StepFormData>({ ...emptyStep });

    const actionOption = actions.find(
        (action) => action.value === stepForm.data.action,
    );
    const shows = (field: string): boolean =>
        actionOption?.fields.includes(field) ?? false;

    const submitWorkflow = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        workflowForm.put(urls.automations.update(workflow.id), {
            preserveScroll: true,
        });
    };

    const startEditingStep = (step: AutomationStepRow): void => {
        setEditingStep(step);
        stepForm.setData({
            action: step.action,
            label: step.label ?? '',
            body: text(step.config.body),
            subject: text(step.config.subject),
            from: text(step.config.from),
            tag_id: text(step.config.tag_id),
            contact_list_id: text(step.config.contact_list_id),
            check: text(step.config.check) || 'has_tag',
            field: text(step.config.field),
            value: text(step.config.value),
            minutes: text(step.config.minutes) || '60',
            url: text(step.config.url),
            secret: text(step.config.secret),
            on_false_position: text(step.config.on_false_position),
        });
    };

    const cancelEditingStep = (): void => {
        setEditingStep(null);
        stepForm.setData({ ...emptyStep });
        stepForm.clearErrors();
    };

    const submitStep = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: () => cancelEditingStep(),
        };

        if (editingStep) {
            stepForm.put(
                urls.automations.steps.update(workflow.id, editingStep.id),
                options,
            );

            return;
        }

        stepForm.post(urls.automations.steps.store(workflow.id), options);
    };

    const moveStep = (
        step: AutomationStepRow,
        direction: 'up' | 'down',
    ): void => {
        router.patch(
            urls.automations.steps.move(workflow.id, step.id),
            { direction },
            { preserveScroll: true },
        );
    };

    const removeStep = async (step: AutomationStepRow): Promise<void> => {
        const confirmed = await confirm({
            title: `Remove step ${step.position}?`,
            description: 'The remaining steps close the gap it leaves behind.',
            confirmLabel: 'Remove step',
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        router.delete(urls.automations.steps.destroy(workflow.id, step.id), {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title={workflow.name} />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={workflow.name}
                    description={workflow.trigger_summary}
                />

                {!enabled && (
                    <Card className="border-warning/30 bg-warning-soft">
                        <CardHeader className="py-3">
                            <CardTitle className="text-sm font-medium">
                                Automations are switched off
                            </CardTitle>
                            <CardDescription className="text-xs">
                                The definition is kept, but no run starts until
                                the platform turns automations back on.
                            </CardDescription>
                        </CardHeader>
                    </Card>
                )}

                <div className="flex flex-wrap items-center gap-2">
                    <Badge variant={workflow.is_active ? 'success' : 'muted'}>
                        {workflow.is_active ? 'Live' : 'Paused'}
                    </Badge>
                    <Badge variant="secondary">
                        {workflow.runs_count} run
                        {workflow.runs_count === 1 ? '' : 's'}
                    </Badge>
                    <Button
                        size="sm"
                        variant="secondary"
                        onClick={() =>
                            router.patch(
                                urls.automations.toggle(workflow.id),
                                {},
                                { preserveScroll: true },
                            )
                        }
                    >
                        {workflow.is_active ? 'Pause' : 'Resume'}
                    </Button>
                    <Button size="sm" variant="secondary" asChild>
                        <Link href={urls.automations.index}>All workflows</Link>
                    </Button>
                </div>

                <div className="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
                    <Card>
                        <CardHeader className="border-b border-border/60 py-3">
                            <CardTitle className="text-sm font-medium">
                                Steps
                            </CardTitle>
                            <CardDescription className="text-xs">
                                The run works through these in order. A
                                condition may stop early or jump ahead.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3 pt-4">
                            {workflow.steps.length === 0 && (
                                <p className="text-sm text-muted-foreground">
                                    No step yet. Add the first one below; a
                                    workflow without steps never starts a run.
                                </p>
                            )}

                            {workflow.steps.map((step) => (
                                <div
                                    key={step.id}
                                    className="rounded-lg border border-border/60 p-3"
                                >
                                    <div className="flex flex-wrap items-start justify-between gap-2">
                                        <div className="space-y-1">
                                            <p className="text-sm font-medium">
                                                {step.position}. {step.describe}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {step.summary}
                                            </p>
                                        </div>
                                        <Badge variant="outline">
                                            {step.action_label}
                                        </Badge>
                                    </div>

                                    <div className="mt-3 flex flex-wrap gap-2">
                                        <Button
                                            size="sm"
                                            variant="secondary"
                                            disabled={step.position === 1}
                                            onClick={() => moveStep(step, 'up')}
                                        >
                                            <ArrowUp />
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="secondary"
                                            disabled={
                                                step.position ===
                                                workflow.steps.length
                                            }
                                            onClick={() =>
                                                moveStep(step, 'down')
                                            }
                                        >
                                            <ArrowDown />
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="secondary"
                                            onClick={() =>
                                                startEditingStep(step)
                                            }
                                        >
                                            Edit
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="destructive"
                                            onClick={() =>
                                                void removeStep(step)
                                            }
                                        >
                                            <Trash2 />
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="border-b border-border/60 py-3">
                            <CardTitle className="flex items-center gap-2 text-sm font-medium">
                                <Plus className="size-4" />
                                {editingStep
                                    ? `Edit step ${editingStep.position}`
                                    : 'Add a step'}
                            </CardTitle>
                            <CardDescription className="text-xs">
                                {editingStep
                                    ? 'Saving rewrites the configuration this step stores.'
                                    : `This workflow holds at most ${limits.max_steps} steps.`}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="pt-4">
                            <form onSubmit={submitStep} className="space-y-4">
                                <div className="space-y-2">
                                    <Label>Action</Label>
                                    <Select
                                        value={stepForm.data.action}
                                        onValueChange={(value) =>
                                            stepForm.setData('action', value)
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {actions.map((action) => (
                                                <SelectItem
                                                    key={action.value}
                                                    value={action.value}
                                                >
                                                    {action.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {actionOption && (
                                        <p className="text-xs text-muted-foreground">
                                            {actionOption.description}
                                        </p>
                                    )}
                                    {stepForm.errors.action && (
                                        <p className="text-xs text-destructive">
                                            {stepForm.errors.action}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="step-label">Label</Label>
                                    <Input
                                        id="step-label"
                                        value={stepForm.data.label}
                                        onChange={(event) =>
                                            stepForm.setData(
                                                'label',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Optional wording for the timeline"
                                    />
                                </div>

                                {shows('subject') && (
                                    <div className="space-y-2">
                                        <Label htmlFor="step-subject">
                                            Subject
                                        </Label>
                                        <Input
                                            id="step-subject"
                                            value={stepForm.data.subject}
                                            onChange={(event) =>
                                                stepForm.setData(
                                                    'subject',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                )}

                                {shows('body') && (
                                    <div className="space-y-2">
                                        <Label htmlFor="step-body">
                                            Message
                                        </Label>
                                        <Textarea
                                            id="step-body"
                                            rows={3}
                                            value={stepForm.data.body}
                                            onChange={(event) =>
                                                stepForm.setData(
                                                    'body',
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="Hello {{first_name}}, thanks for joining us."
                                        />
                                        {stepForm.errors.body && (
                                            <p className="text-xs text-destructive">
                                                {stepForm.errors.body}
                                            </p>
                                        )}
                                    </div>
                                )}

                                {shows('from') && (
                                    <div className="space-y-2">
                                        <Label htmlFor="step-from">From</Label>
                                        <Input
                                            id="step-from"
                                            value={stepForm.data.from}
                                            onChange={(event) =>
                                                stepForm.setData(
                                                    'from',
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="The tenant's approved sender"
                                        />
                                    </div>
                                )}

                                {shows('tag_id') && (
                                    <div className="space-y-2">
                                        <Label>Tag</Label>
                                        <Select
                                            value={stepForm.data.tag_id}
                                            onValueChange={(value) =>
                                                stepForm.setData(
                                                    'tag_id',
                                                    value,
                                                )
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
                                        {stepForm.errors.tag_id && (
                                            <p className="text-xs text-destructive">
                                                {stepForm.errors.tag_id}
                                            </p>
                                        )}
                                    </div>
                                )}

                                {shows('contact_list_id') && (
                                    <div className="space-y-2">
                                        <Label>Contact list</Label>
                                        <Select
                                            value={
                                                stepForm.data.contact_list_id
                                            }
                                            onValueChange={(value) =>
                                                stepForm.setData(
                                                    'contact_list_id',
                                                    value,
                                                )
                                            }
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Choose a list" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {contactLists.map((list) => (
                                                    <SelectItem
                                                        key={list.id}
                                                        value={String(list.id)}
                                                    >
                                                        {list.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        {stepForm.errors.contact_list_id && (
                                            <p className="text-xs text-destructive">
                                                {
                                                    stepForm.errors
                                                        .contact_list_id
                                                }
                                            </p>
                                        )}
                                    </div>
                                )}

                                {shows('check') && (
                                    <div className="space-y-2">
                                        <Label>Check</Label>
                                        <Select
                                            value={stepForm.data.check}
                                            onValueChange={(value) =>
                                                stepForm.setData('check', value)
                                            }
                                        >
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {conditions.map((condition) => (
                                                    <SelectItem
                                                        key={condition.value}
                                                        value={condition.value}
                                                    >
                                                        {condition.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        {stepForm.errors.check && (
                                            <p className="text-xs text-destructive">
                                                {stepForm.errors.check}
                                            </p>
                                        )}
                                    </div>
                                )}

                                {shows('field') &&
                                    stepForm.data.check === 'custom_field' && (
                                        <div className="grid gap-3 sm:grid-cols-2">
                                            <div className="space-y-2">
                                                <Label htmlFor="step-field">
                                                    Custom field
                                                </Label>
                                                <Input
                                                    id="step-field"
                                                    value={stepForm.data.field}
                                                    onChange={(event) =>
                                                        stepForm.setData(
                                                            'field',
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                                {stepForm.errors.field && (
                                                    <p className="text-xs text-destructive">
                                                        {stepForm.errors.field}
                                                    </p>
                                                )}
                                            </div>
                                            <div className="space-y-2">
                                                <Label htmlFor="step-value">
                                                    Equals
                                                </Label>
                                                <Input
                                                    id="step-value"
                                                    value={stepForm.data.value}
                                                    onChange={(event) =>
                                                        stepForm.setData(
                                                            'value',
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                            </div>
                                        </div>
                                    )}

                                {shows('on_false_position') && (
                                    <div className="space-y-2">
                                        <Label htmlFor="step-false">
                                            When the check fails, jump to step
                                        </Label>
                                        <Input
                                            id="step-false"
                                            type="number"
                                            min={1}
                                            max={limits.max_steps}
                                            value={
                                                stepForm.data.on_false_position
                                            }
                                            onChange={(event) =>
                                                stepForm.setData(
                                                    'on_false_position',
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="Leave empty to end the workflow"
                                        />
                                        {stepForm.errors.on_false_position && (
                                            <p className="text-xs text-destructive">
                                                {
                                                    stepForm.errors
                                                        .on_false_position
                                                }
                                            </p>
                                        )}
                                    </div>
                                )}

                                {shows('minutes') && (
                                    <div className="space-y-2">
                                        <Label htmlFor="step-minutes">
                                            Wait for (minutes)
                                        </Label>
                                        <Input
                                            id="step-minutes"
                                            type="number"
                                            min={1}
                                            max={limits.max_wait_minutes}
                                            value={stepForm.data.minutes}
                                            onChange={(event) =>
                                                stepForm.setData(
                                                    'minutes',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                        {stepForm.errors.minutes && (
                                            <p className="text-xs text-destructive">
                                                {stepForm.errors.minutes}
                                            </p>
                                        )}
                                    </div>
                                )}

                                {shows('url') && (
                                    <div className="space-y-2">
                                        <Label htmlFor="step-url">
                                            Endpoint URL
                                        </Label>
                                        <Input
                                            id="step-url"
                                            type="url"
                                            value={stepForm.data.url}
                                            onChange={(event) =>
                                                stepForm.setData(
                                                    'url',
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="https://example.com/hooks/contact"
                                        />
                                        {stepForm.errors.url && (
                                            <p className="text-xs text-destructive">
                                                {stepForm.errors.url}
                                            </p>
                                        )}
                                    </div>
                                )}

                                {shows('secret') && (
                                    <div className="space-y-2">
                                        <Label htmlFor="step-secret">
                                            Signing secret
                                        </Label>
                                        <Input
                                            id="step-secret"
                                            value={stepForm.data.secret}
                                            onChange={(event) =>
                                                stepForm.setData(
                                                    'secret',
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="Leave empty to keep or issue one"
                                        />
                                    </div>
                                )}

                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        type="submit"
                                        disabled={stepForm.processing}
                                    >
                                        {editingStep ? 'Save step' : 'Add step'}
                                    </Button>
                                    {editingStep && (
                                        <Button
                                            type="button"
                                            variant="secondary"
                                            onClick={cancelEditingStep}
                                        >
                                            Cancel
                                        </Button>
                                    )}
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
                    <Card>
                        <CardHeader className="border-b border-border/60 py-3">
                            <CardTitle className="text-sm font-medium">
                                Recent runs
                            </CardTitle>
                            <CardDescription className="text-xs">
                                The last 25 contacts this workflow worked
                                through.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-2 pt-4">
                            {runs.length === 0 && (
                                <p className="text-sm text-muted-foreground">
                                    No run yet. The workflow starts the moment
                                    its trigger fires.
                                </p>
                            )}

                            {runs.map((run) => (
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
                                            {run.contact?.name ??
                                                `Run #${run.id}`}
                                        </Link>
                                        <p className="text-xs text-muted-foreground">
                                            Step {run.current_position}
                                            {run.detail
                                                ? ` · ${run.detail}`
                                                : ''}
                                        </p>
                                    </div>
                                    <Badge
                                        variant={
                                            statusVariant[run.status] ??
                                            'secondary'
                                        }
                                    >
                                        {run.status_label}
                                    </Badge>
                                </div>
                            ))}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="border-b border-border/60 py-3">
                            <CardTitle className="text-sm font-medium">
                                Workflow settings
                            </CardTitle>
                            <CardDescription className="text-xs">
                                Renaming or moving the trigger does not restart
                                runs already in flight.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="pt-4">
                            <form
                                onSubmit={submitWorkflow}
                                className="space-y-4"
                            >
                                <div className="space-y-2">
                                    <Label htmlFor="settings-name">Name</Label>
                                    <Input
                                        id="settings-name"
                                        value={workflowForm.data.name}
                                        onChange={(event) =>
                                            workflowForm.setData(
                                                'name',
                                                event.target.value,
                                            )
                                        }
                                        required
                                    />
                                    {workflowForm.errors.name && (
                                        <p className="text-xs text-destructive">
                                            {workflowForm.errors.name}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="settings-description">
                                        Description
                                    </Label>
                                    <Textarea
                                        id="settings-description"
                                        rows={2}
                                        value={workflowForm.data.description}
                                        onChange={(event) =>
                                            workflowForm.setData(
                                                'description',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label>Trigger</Label>
                                    <Select
                                        value={workflowForm.data.trigger}
                                        onValueChange={(value) =>
                                            workflowForm.setData(
                                                'trigger',
                                                value,
                                            )
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
                                    {workflowForm.errors.trigger && (
                                        <p className="text-xs text-destructive">
                                            {workflowForm.errors.trigger}
                                        </p>
                                    )}
                                </div>

                                {workflowForm.data.trigger ===
                                    'contact.tagged' && (
                                    <div className="space-y-2">
                                        <Label>Tag</Label>
                                        <Select
                                            value={workflowForm.data.tag_id}
                                            onValueChange={(value) =>
                                                workflowForm.setData(
                                                    'tag_id',
                                                    value,
                                                )
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
                                        {workflowForm.errors.tag_id && (
                                            <p className="text-xs text-destructive">
                                                {workflowForm.errors.tag_id}
                                            </p>
                                        )}
                                    </div>
                                )}

                                {workflowForm.data.trigger === 'schedule' && (
                                    <div className="space-y-2">
                                        <Label htmlFor="settings-hour">
                                            Hour of the day (0-23)
                                        </Label>
                                        <Input
                                            id="settings-hour"
                                            type="number"
                                            min={0}
                                            max={23}
                                            value={workflowForm.data.hour}
                                            onChange={(event) =>
                                                workflowForm.setData(
                                                    'hour',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                        {workflowForm.errors.hour && (
                                            <p className="text-xs text-destructive">
                                                {workflowForm.errors.hour}
                                            </p>
                                        )}
                                    </div>
                                )}

                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={workflowForm.data.is_active}
                                        onChange={(event) =>
                                            workflowForm.setData(
                                                'is_active',
                                                event.target.checked,
                                            )
                                        }
                                    />
                                    Live: start a run when the trigger fires
                                </label>

                                <Button
                                    type="submit"
                                    disabled={workflowForm.processing}
                                >
                                    Save workflow
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}
