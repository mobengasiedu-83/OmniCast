import { Head, router, useForm } from '@inertiajs/react';
import { RotateCw, Webhook } from 'lucide-react';
import type { FormEvent } from 'react';
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
    clients: { id: number; name: string }[];
    filters: { client_id: number | null };
    enabled: boolean;
    newSecret: { id: number; secret: string } | null;
};

type FormData = {
    client_id: string;
    name: string;
    url: string;
    description: string;
    events: string[];
};

const statusTone: Record<
    string,
    'success' | 'destructive' | 'warning' | 'muted'
> = {
    delivered: 'success',
    failed: 'destructive',
    delivering: 'warning',
    pending: 'muted',
};

function EventPicker({
    events,
    selected,
    onToggle,
}: {
    events: WebhookEventCatalogue[];
    selected: string[];
    onToggle: (value: string) => void;
}) {
    return (
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
                                checked={selected.includes(event.value)}
                                onCheckedChange={() => onToggle(event.value)}
                            />
                            <span>{event.label}</span>
                        </label>
                    ))}
                </div>
            ))}
        </div>
    );
}

function EndpointAdminActions({ endpoint }: { endpoint: WebhookEndpointRow }) {
    return (
        <div className="flex flex-wrap gap-2">
            <Button
                size="sm"
                variant="secondary"
                onClick={() =>
                    router.post(
                        urls.admin.webhooks.test(endpoint.id),
                        {},
                        { preserveScroll: true },
                    )
                }
            >
                Send test
            </Button>
            <Button
                size="sm"
                variant="secondary"
                onClick={() =>
                    router.patch(
                        urls.admin.webhooks.toggle(endpoint.id),
                        {},
                        { preserveScroll: true },
                    )
                }
            >
                {endpoint.is_active ? 'Pause' : 'Resume'}
            </Button>
            <Button
                size="sm"
                variant="secondary"
                onClick={() =>
                    router.patch(
                        urls.admin.webhooks.rotate(endpoint.id),
                        {},
                        { preserveScroll: true },
                    )
                }
            >
                New secret
            </Button>
            <Button
                size="sm"
                variant="destructive"
                onClick={() =>
                    router.delete(urls.admin.webhooks.destroy(endpoint.id), {
                        preserveScroll: true,
                    })
                }
            >
                Delete
            </Button>
        </div>
    );
}

export default function AdminWebhooks({
    endpoints,
    deliveries,
    events,
    clients,
    filters,
    enabled,
    newSecret,
}: PageProps) {
    const form = useForm<FormData>({
        client_id: filters.client_id === null ? '' : String(filters.client_id),
        name: '',
        url: '',
        description: '',
        events: [],
    });

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

        form.post(urls.admin.webhooks.store, {
            preserveScroll: true,
            onSuccess: () =>
                form.reset('name', 'url', 'description', 'events'),
        });
    };

    const filter = (clientId: string): void => {
        router.get(
            urls.admin.webhooks.index,
            clientId === '' ? {} : { client_id: clientId },
            { preserveState: true, preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Webhooks" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Webhooks"
                    description="Every outbound callback the platform sends, for support and integration debugging."
                />

                <div className="flex flex-wrap items-center gap-2">
                    <Label htmlFor="admin-webhook-filter">Tenant</Label>
                    <select
                        id="admin-webhook-filter"
                        className="h-9 rounded-md border border-border bg-background px-3 text-sm"
                        value={
                            filters.client_id === null
                                ? ''
                                : String(filters.client_id)
                        }
                        onChange={(event) => filter(event.target.value)}
                    >
                        <option value="">All tenants</option>
                        {clients.map((client) => (
                            <option key={client.id} value={client.id}>
                                {client.name}
                            </option>
                        ))}
                    </select>
                </div>

                {!enabled && (
                    <Card className="border-warning/30 bg-warning-soft">
                        <CardHeader className="py-3">
                            <CardTitle className="text-sm font-medium">
                                Outbound delivery is switched off
                            </CardTitle>
                            <CardDescription className="text-xs">
                                <code className="rounded bg-muted px-1">
                                    WEBHOOKS_ENABLED
                                </code>{' '}
                                is false, so no delivery row is written.
                            </CardDescription>
                        </CardHeader>
                    </Card>
                )}

                {newSecret && (
                    <Card className="border-success/30 bg-success-soft">
                        <CardHeader className="py-3">
                            <CardTitle className="text-sm font-medium">
                                New signing secret for endpoint #{newSecret.id}
                            </CardTitle>
                            <CardDescription className="text-xs">
                                Shown once. The subscriber has to be updated
                                before its next event.
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
                            Subscribe an endpoint
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="pt-4">
                        <form
                            onSubmit={submit}
                            className="grid gap-4 md:grid-cols-2"
                        >
                            <div className="space-y-2">
                                <Label htmlFor="admin-webhook-client">
                                    Tenant
                                </Label>
                                <select
                                    id="admin-webhook-client"
                                    className="h-9 w-full rounded-md border border-border bg-background px-3 text-sm"
                                    value={form.data.client_id}
                                    onChange={(event) =>
                                        form.setData(
                                            'client_id',
                                            event.target.value,
                                        )
                                    }
                                >
                                    <option value="">
                                        Platform (all tenants)
                                    </option>
                                    {clients.map((client) => (
                                        <option
                                            key={client.id}
                                            value={client.id}
                                        >
                                            {client.name}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="admin-webhook-name">Name</Label>
                                <Input
                                    id="admin-webhook-name"
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

                            <div className="space-y-2 md:col-span-2">
                                <Label htmlFor="admin-webhook-url">URL</Label>
                                <Input
                                    id="admin-webhook-url"
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

                            <div className="space-y-3 md:col-span-2">
                                <Label>Events</Label>
                                <EventPicker
                                    events={events}
                                    selected={form.data.events}
                                    onToggle={toggleEvent}
                                />
                                {form.errors.events && (
                                    <p className="text-xs text-destructive">
                                        {form.errors.events}
                                    </p>
                                )}
                            </div>

                            <div className="md:col-span-2">
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    Subscribe endpoint
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="border-b border-border/60 py-3">
                        <CardTitle className="text-sm font-medium">
                            Subscriptions
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="divide-y divide-border/60 pt-0">
                        {endpoints.length === 0 && (
                            <p className="py-6 text-sm text-muted-foreground">
                                No subscription matches this filter.
                            </p>
                        )}

                        {endpoints.map((endpoint) => (
                            <div
                                key={endpoint.id}
                                className="flex flex-wrap items-start justify-between gap-3 py-4"
                            >
                                <div>
                                    <p className="flex items-center gap-2 font-medium">
                                        {endpoint.name}
                                        <Badge
                                            variant={
                                                endpoint.is_active &&
                                                endpoint.disabled_at === null
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
                                        <span className="text-xs text-muted-foreground">
                                            {endpoint.client?.name ?? 'Platform'}
                                        </span>
                                    </p>
                                    <p className="text-xs break-all text-muted-foreground">
                                        {endpoint.url}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {endpoint.masked_secret} ·{' '}
                                        {endpoint.consecutive_failures}{' '}
                                        consecutive failure(s)
                                    </p>
                                </div>

                                <EndpointAdminActions endpoint={endpoint} />
                            </div>
                        ))}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="border-b border-border/60 py-3">
                        <CardTitle className="flex items-center gap-2 text-sm font-medium">
                            <RotateCw className="size-4" />
                            Recent deliveries
                        </CardTitle>
                        <CardDescription className="text-xs">
                            A failed delivery can be queued again with the body
                            it was created with, so the subscriber receives the
                            event it missed rather than a synthetic one.
                        </CardDescription>
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
                                        <p className="text-xs text-muted-foreground">
                                            {delivery.response_status ?? '—'} ·{' '}
                                            {delivery.attempts} attempt(s)
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Badge
                                            variant={
                                                statusTone[delivery.status] ??
                                                'muted'
                                            }
                                        >
                                            {delivery.status}
                                        </Badge>
                                        <Button
                                            size="sm"
                                            variant="secondary"
                                            onClick={() =>
                                                router.post(
                                                    urls.admin.webhooks.replay(
                                                        delivery.id,
                                                    ),
                                                    {},
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            Replay
                                        </Button>
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
