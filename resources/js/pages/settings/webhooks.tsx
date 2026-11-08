import { Head, router, useForm } from '@inertiajs/react';
import { FlaskConical, RotateCcw, Trash2, Webhook } from 'lucide-react';
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
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { urls } from '@/lib/urls';
import type {
    WebhookDeliveryRow,
    WebhookEndpointRow,
    WebhookEventCatalogue,
} from '@/types';

type PageProps = {
    endpoints: WebhookEndpointRow[];
    deliveries: WebhookDeliveryRow[];
    events: WebhookEventCatalogue[];
    signature: { header: string; tolerance: number; version: string };
    enabled: boolean;
    newSecret: { id: number; secret: string } | null;
};

type FormData = {
    name: string;
    url: string;
    description: string;
    events: string[];
    is_active: boolean;
};

const emptyForm: FormData = {
    name: '',
    url: '',
    description: '',
    events: [],
    is_active: true,
};

const statusTone: Record<string, 'success' | 'destructive' | 'warning' | 'muted'> =
    {
        delivered: 'success',
        failed: 'destructive',
        delivering: 'warning',
        pending: 'muted',
    };

type EndpointActionsProps = {
    endpoint: WebhookEndpointRow;
    onEdit: (endpoint: WebhookEndpointRow) => void;
    onDelete: (endpoint: WebhookEndpointRow) => Promise<void>;
};

function EndpointActions({ endpoint, onEdit, onDelete }: EndpointActionsProps) {
    const act = (
        url: string,
        method: 'post' | 'patch',
    ): void => {
        router[method](url, {}, { preserveScroll: true });
    };

    return (
        <div className="flex flex-wrap gap-2">
            <Button
                size="sm"
                variant="secondary"
                onClick={() => act(urls.account.webhooks.test(endpoint.id), 'post')}
            >
                <FlaskConical />
                Send test
            </Button>
            <Button
                size="sm"
                variant="secondary"
                onClick={() =>
                    act(urls.account.webhooks.toggle(endpoint.id), 'patch')
                }
            >
                {endpoint.is_active ? 'Pause' : 'Resume'}
            </Button>
            <Button
                size="sm"
                variant="secondary"
                onClick={() =>
                    act(urls.account.webhooks.rotate(endpoint.id), 'patch')
                }
            >
                <RotateCcw />
                New secret
            </Button>
            <Button size="sm" variant="secondary" onClick={() => onEdit(endpoint)}>
                Edit
            </Button>
            <Button
                size="sm"
                variant="destructive"
                onClick={() => void onDelete(endpoint)}
            >
                <Trash2 />
            </Button>
        </div>
    );
}

export default function SettingsWebhooks({
    endpoints,
    deliveries,
    events,
    signature,
    enabled,
    newSecret,
}: PageProps) {
    const confirm = useConfirm();
    const [editing, setEditing] = useState<WebhookEndpointRow | null>(null);

    const form = useForm<FormData>({ ...emptyForm });

    const startEditing = (endpoint: WebhookEndpointRow): void => {
        setEditing(endpoint);
        form.setData({
            name: endpoint.name,
            url: endpoint.url,
            description: endpoint.description ?? '',
            events: endpoint.events,
            is_active: endpoint.is_active,
        });
    };

    const cancelEditing = (): void => {
        setEditing(null);
        form.setData({ ...emptyForm });
        form.clearErrors();
    };

    const toggleEvent = (value: string): void => {
        form.setData(
            'events',
            form.data.events.includes(value)
                ? form.data.events.filter((event) => event !== value)
                : [...form.data.events, value],
        );
    };

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        if (editing) {
            form.put(urls.account.webhooks.update(editing.id), {
                preserveScroll: true,
                onSuccess: () => cancelEditing(),
            });

            return;
        }

        form.post(urls.account.webhooks.store, {
            preserveScroll: true,
            onSuccess: () => form.setData({ ...emptyForm }),
        });
    };

    const remove = async (endpoint: WebhookEndpointRow): Promise<void> => {
        const confirmed = await confirm({
            title: `Delete ${endpoint.name}?`,
            description:
                'The URL stops receiving events and its delivery history is deleted.',
            confirmLabel: 'Delete endpoint',
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        router.delete(urls.account.webhooks.destroy(endpoint.id), {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Webhooks" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Webhooks"
                    description="Callbacks for campaign and delivery events, signed so the receiver can trust them."
                />

                {!enabled && (
                    <Card className="border-warning/30 bg-warning-soft">
                        <CardHeader className="py-3">
                            <CardTitle className="text-sm font-medium">
                                Outbound webhooks are switched off
                            </CardTitle>
                            <CardDescription className="text-xs">
                                Subscriptions are kept, but nothing is queued
                                until the platform turns delivery back on.
                            </CardDescription>
                        </CardHeader>
                    </Card>
                )}

                {newSecret && (
                    <Card className="border-success/30 bg-success-soft">
                        <CardHeader className="py-3">
                            <CardTitle className="text-sm font-medium">
                                Copy the new signing secret for endpoint #
                                {newSecret.id}
                            </CardTitle>
                            <CardDescription className="text-xs">
                                This is the only time the secret is shown.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Input readOnly value={newSecret.secret} />
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader className="border-b border-border/60 py-3">
                        <CardTitle className="flex items-center gap-2 text-sm font-medium">
                            <Webhook className="size-4" />
                            {editing
                                ? `Edit ${editing.name}`
                                : 'Add an endpoint'}
                        </CardTitle>
                        <CardDescription className="text-xs">
                            Every request carries{' '}
                            <code className="rounded bg-muted px-1">
                                {signature.header}
                            </code>{' '}
                            with an HMAC-SHA256 signature over the body, valid
                            for {signature.tolerance} seconds. Payloads are
                            versioned; this tenant is on {signature.version}.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="pt-4">
                        <form
                            onSubmit={submit}
                            className="grid gap-4 md:grid-cols-2"
                        >
                            <div className="space-y-2">
                                <Label htmlFor="webhook-name">Name</Label>
                                <Input
                                    id="webhook-name"
                                    value={form.data.name}
                                    onChange={(event) =>
                                        form.setData('name', event.target.value)
                                    }
                                    required
                                />
                                {form.errors.name && (
                                    <p className="text-xs text-destructive">
                                        {form.errors.name}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="webhook-url">
                                    Endpoint URL
                                </Label>
                                <Input
                                    id="webhook-url"
                                    type="url"
                                    placeholder="https://example.com/hooks/omnicast"
                                    value={form.data.url}
                                    onChange={(event) =>
                                        form.setData('url', event.target.value)
                                    }
                                    required
                                />
                                {form.errors.url && (
                                    <p className="text-xs text-destructive">
                                        {form.errors.url}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2 md:col-span-2">
                                <Label htmlFor="webhook-description">
                                    Description
                                </Label>
                                <Input
                                    id="webhook-description"
                                    value={form.data.description}
                                    onChange={(event) =>
                                        form.setData(
                                            'description',
                                            event.target.value,
                                        )
                                    }
                                />
                            </div>

                            <div className="space-y-3 md:col-span-2">
                                <Label>Events</Label>
                                <div className="grid gap-3 sm:grid-cols-3">
                                    {events.map((group) => (
                                        <div
                                            key={group.group}
                                            className="space-y-2 rounded-lg border border-border/60 p-3"
                                        >
                                            <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                                {group.group}
                                            </p>
                                            {group.events.map((event) => (
                                                <label
                                                    key={event.value}
                                                    className="flex items-start gap-2 text-sm"
                                                >
                                                    <Checkbox
                                                        checked={form.data.events.includes(
                                                            event.value,
                                                        )}
                                                        onCheckedChange={() =>
                                                            toggleEvent(
                                                                event.value,
                                                            )
                                                        }
                                                    />
                                                    <span>{event.label}</span>
                                                </label>
                                            ))}
                                        </div>
                                    ))}
                                </div>
                                {form.errors.events && (
                                    <p className="text-xs text-destructive">
                                        {form.errors.events}
                                    </p>
                                )}
                            </div>

                            <div className="flex items-center gap-2 md:col-span-2">
                                <Checkbox
                                    id="webhook-active"
                                    checked={form.data.is_active}
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'is_active',
                                            checked === true,
                                        )
                                    }
                                />
                                <Label htmlFor="webhook-active">
                                    Receive events immediately
                                </Label>
                            </div>

                            <div className="flex gap-2 md:col-span-2">
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    {editing ? 'Save endpoint' : 'Add endpoint'}
                                </Button>
                                {editing && (
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        onClick={cancelEditing}
                                    >
                                        Cancel
                                    </Button>
                                )}
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="border-b border-border/60 py-3">
                        <CardTitle className="text-sm font-medium">
                            Endpoints
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="divide-y divide-border/60 pt-0">
                        {endpoints.length === 0 && (
                            <p className="py-6 text-sm text-muted-foreground">
                                No endpoint yet. Add one to receive campaign and
                                delivery events.
                            </p>
                        )}

                        {endpoints.map((endpoint) => (
                            <div key={endpoint.id} className="space-y-3 py-4">
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p className="flex items-center gap-2 font-medium">
                                            {endpoint.name}
                                            <Badge
                                                variant={
                                                    endpoint.is_active &&
                                                    endpoint.disabled_at ===
                                                        null
                                                        ? 'success'
                                                        : 'muted'
                                                }
                                            >
                                                {endpoint.disabled_at !== null
                                                    ? 'Auto-disabled'
                                                    : endpoint.is_active
                                                      ? 'Active'
                                                      : 'Paused'}
                                            </Badge>
                                        </p>
                                        <p className="text-xs break-all text-muted-foreground">
                                            {endpoint.url}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            Secret {endpoint.masked_secret} ·{' '}
                                            {endpoint.events.length} event(s) ·{' '}
                                            {endpoint.consecutive_failures}{' '}
                                            consecutive failure(s)
                                        </p>
                                    </div>

                                    <EndpointActions
                                        endpoint={endpoint}
                                        onEdit={startEditing}
                                        onDelete={remove}
                                    />
                                </div>

                                <div className="flex flex-wrap gap-1">
                                    {endpoint.events.map((event) => (
                                        <Badge key={event} variant="outline">
                                            {event}
                                        </Badge>
                                    ))}
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="border-b border-border/60 py-3">
                        <CardTitle className="text-sm font-medium">
                            Recent deliveries
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="pt-0">
                        {deliveries.length === 0 && (
                            <p className="py-6 text-sm text-muted-foreground">
                                No delivery has been attempted yet.
                            </p>
                        )}

                        <div className="divide-y divide-border/60">
                            {deliveries.map((delivery) => (
                                <div
                                    key={delivery.id}
                                    className="flex flex-wrap items-center justify-between gap-2 py-3 text-sm"
                                >
                                    <div>
                                        <p className="font-medium">
                                            {delivery.event}
                                            <span className="ml-2 text-xs text-muted-foreground">
                                                {delivery.endpoint.name}
                                            </span>
                                        </p>
                                        {delivery.error_message && (
                                            <p className="text-xs text-destructive">
                                                {delivery.error_message}
                                            </p>
                                        )}
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <span className="text-xs text-muted-foreground">
                                            {delivery.response_status ?? '—'} ·{' '}
                                            {delivery.attempts} attempt(s)
                                        </span>
                                        <Badge
                                            variant={
                                                statusTone[delivery.status] ??
                                                'muted'
                                            }
                                        >
                                            {delivery.status}
                                        </Badge>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
