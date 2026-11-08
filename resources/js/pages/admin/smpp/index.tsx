import { Head, router, useForm } from '@inertiajs/react';
import { Radio, Server } from 'lucide-react';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { urls } from '@/lib/urls';
import type {
    SmppAccountRow,
    SmppMessageRow,
    SmppSessionRow,
} from '@/types';

type PageProps = {
    accounts: SmppAccountRow[];
    sessions: SmppSessionRow[];
    messages: SmppMessageRow[];
    summary: {
        accounts: number;
        active_sessions: number;
        pending_receipts: number;
        accepted_today: number;
        failed_today: number;
    };
    bindModes: string[];
    clients: { id: number; name: string }[];
    filters: { client_id: number | null };
    server: { enabled: boolean; port: number; max_sessions: number };
    newPassword: { system_id: string; password: string } | null;
};

type FormData = {
    client_id: string;
    name: string;
    system_id: string;
    bind_mode: string;
    allowed_sources: string;
    throughput_per_second: string;
};

const statusTone: Record<
    string,
    'success' | 'destructive' | 'warning' | 'muted'
> = {
    delivered: 'success',
    submitted: 'warning',
    accepted: 'muted',
    failed: 'destructive',
    rejected: 'destructive',
};

export default function AdminSmpp({
    accounts,
    sessions,
    messages,
    summary,
    bindModes,
    clients,
    filters,
    server,
    newPassword,
}: PageProps) {
    const form = useForm<FormData>({
        client_id: clients[0] === undefined ? '' : String(clients[0].id),
        name: '',
        system_id: '',
        bind_mode: 'transceiver',
        allowed_sources: '',
        throughput_per_second: '10',
    });

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        form.post(urls.admin.smpp.store, {
            preserveScroll: true,
            onSuccess: () =>
                form.reset('name', 'system_id', 'allowed_sources'),
        });
    };

    const filter = (clientId: string): void => {
        router.get(
            urls.admin.smpp.index,
            clientId === '' ? {} : { client_id: clientId },
            { preserveState: true, preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="SMPP" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="SMPP"
                    description="The accounts an ESME binds with, the sessions they hold and the messages they submitted."
                />

                <div className="flex flex-wrap items-center gap-3 text-sm">
                    <Badge variant={server.enabled ? 'success' : 'muted'}>
                        {server.enabled
                            ? `Listening on port ${server.port}`
                            : 'Server switched off'}
                    </Badge>
                    <span className="text-xs text-muted-foreground">
                        {summary.accounts} account(s) ·{' '}
                        {summary.active_sessions} bound session(s) of{' '}
                        {server.max_sessions} · {summary.pending_receipts}{' '}
                        receipt(s) waiting · {summary.accepted_today}{' '}
                        submitted today · {summary.failed_today} failed
                    </span>
                    <select
                        className="h-8 rounded-md border border-border bg-background px-2 text-sm"
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

                {newPassword && (
                    <Card className="border-success/30 bg-success-soft">
                        <CardHeader className="py-3">
                            <CardTitle className="text-sm font-medium">
                                Password for {newPassword.system_id}
                            </CardTitle>
                            <CardDescription className="text-xs">
                                Shown once. The server only keeps the hash.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Input readOnly value={newPassword.password} />
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader className="border-b border-border/60 py-3">
                        <CardTitle className="flex items-center gap-2 text-sm font-medium">
                            <Radio className="size-4" />
                            Issue an account
                        </CardTitle>
                        <CardDescription className="text-xs">
                            A transceiver account may bind any mode; a one-way
                            account is refused the other direction. Leave the
                            sender list empty to allow any source.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="pt-4">
                        <form
                            onSubmit={submit}
                            className="grid gap-4 md:grid-cols-2"
                        >
                            <div className="space-y-2">
                                <Label htmlFor="smpp-client">Tenant</Label>
                                <select
                                    id="smpp-client"
                                    className="h-9 w-full rounded-md border border-border bg-background px-3 text-sm"
                                    value={form.data.client_id}
                                    onChange={(event) =>
                                        form.setData(
                                            'client_id',
                                            event.target.value,
                                        )
                                    }
                                    required
                                >
                                    <option value="">Select a tenant</option>
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
                                <Label htmlFor="smpp-name">Name</Label>
                                <Input
                                    id="smpp-name"
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
                                <Label htmlFor="smpp-system-id">
                                    System id
                                </Label>
                                <Input
                                    id="smpp-system-id"
                                    value={form.data.system_id}
                                    onChange={(event) =>
                                        form.setData(
                                            'system_id',
                                            event.target.value.toUpperCase(),
                                        )
                                    }
                                    maxLength={16}
                                    required
                                />
                                {form.errors.system_id && (
                                    <p className="text-xs text-destructive">
                                        {form.errors.system_id}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="smpp-bind-mode">
                                    Bind mode
                                </Label>
                                <select
                                    id="smpp-bind-mode"
                                    className="h-9 w-full rounded-md border border-border bg-background px-3 text-sm"
                                    value={form.data.bind_mode}
                                    onChange={(event) =>
                                        form.setData(
                                            'bind_mode',
                                            event.target.value,
                                        )
                                    }
                                >
                                    {bindModes.map((mode) => (
                                        <option key={mode} value={mode}>
                                            {mode}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="smpp-sources">
                                    Allowed senders
                                </Label>
                                <Input
                                    id="smpp-sources"
                                    placeholder="OMNICAST, 233240000000"
                                    value={form.data.allowed_sources}
                                    onChange={(event) =>
                                        form.setData(
                                            'allowed_sources',
                                            event.target.value,
                                        )
                                    }
                                />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="smpp-throughput">
                                    Messages per second
                                </Label>
                                <Input
                                    id="smpp-throughput"
                                    type="number"
                                    min={1}
                                    max={500}
                                    value={form.data.throughput_per_second}
                                    onChange={(event) =>
                                        form.setData(
                                            'throughput_per_second',
                                            event.target.value,
                                        )
                                    }
                                />
                            </div>

                            <div className="md:col-span-2">
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    Issue account
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="border-b border-border/60 py-3">
                        <CardTitle className="flex items-center gap-2 text-sm font-medium">
                            <Server className="size-4" />
                            Accounts
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="divide-y divide-border/60 pt-0">
                        {accounts.length === 0 && (
                            <p className="py-6 text-sm text-muted-foreground">
                                No SMPP account has been issued yet.
                            </p>
                        )}

                        {accounts.map((account) => (
                            <div
                                key={account.id}
                                className="flex flex-wrap items-start justify-between gap-3 py-4"
                            >
                                <div>
                                    <p className="flex items-center gap-2 font-medium">
                                        {account.system_id}
                                        <Badge
                                            variant={
                                                account.is_active
                                                    ? 'success'
                                                    : 'muted'
                                            }
                                        >
                                            {account.is_active
                                                ? 'Active'
                                                : 'Disabled'}
                                        </Badge>
                                        <span className="text-xs text-muted-foreground">
                                            {account.client?.name ?? '—'} ·{' '}
                                            {account.bind_mode_label} ·{' '}
                                            {account.throughput_per_second}{' '}
                                            msg/s
                                        </span>
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {account.name} ·{' '}
                                        {account.messages_count} message(s) ·
                                        senders:{' '}
                                        {account.allowed_sources.length === 0
                                            ? 'any'
                                            : account.allowed_sources.join(', ')}
                                    </p>
                                </div>

                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        size="sm"
                                        variant="secondary"
                                        onClick={() =>
                                            router.patch(
                                                urls.admin.smpp.toggle(
                                                    account.id,
                                                ),
                                                {},
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        {account.is_active
                                            ? 'Disable'
                                            : 'Enable'}
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="secondary"
                                        onClick={() =>
                                            router.patch(
                                                urls.admin.smpp.rotate(
                                                    account.id,
                                                ),
                                                {},
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        New password
                                    </Button>
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="border-b border-border/60 py-3">
                        <CardTitle className="text-sm font-medium">
                            Bound sessions
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="divide-y divide-border/60 pt-0">
                        {sessions.length === 0 && (
                            <p className="py-6 text-sm text-muted-foreground">
                                No ESME is bound right now.
                            </p>
                        )}

                        {sessions.map((session) => (
                            <div
                                key={session.id}
                                className="flex flex-wrap items-center justify-between gap-2 py-3 text-sm"
                            >
                                <div>
                                    <p className="font-medium">
                                        {session.system_id}
                                        <span className="ml-2 text-xs text-muted-foreground">
                                            {session.mode} ·{' '}
                                            {session.remote_address ?? '—'}
                                        </span>
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        bound {session.bound_at} · last seen{' '}
                                        {session.last_seen_at ?? '—'}
                                    </p>
                                </div>
                                <span className="text-xs text-muted-foreground">
                                    {session.pdus_in} in / {session.pdus_out}{' '}
                                    out
                                </span>
                            </div>
                        ))}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="border-b border-border/60 py-3">
                        <CardTitle className="text-sm font-medium">
                            Latest submissions
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="divide-y divide-border/60 pt-0">
                        {messages.length === 0 && (
                            <p className="py-6 text-sm text-muted-foreground">
                                No `submit_sm` has been ingested yet.
                            </p>
                        )}

                        {messages.map((message) => (
                            <div
                                key={message.id}
                                className="flex flex-wrap items-center justify-between gap-2 py-3 text-sm"
                            >
                                <div>
                                    <p className="font-medium">
                                        {message.destination_address}
                                        <span className="ml-2 text-xs text-muted-foreground">
                                            from {message.source_address} ·{' '}
                                            {message.account ?? '—'}
                                        </span>
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {message.message_id} ·{' '}
                                        {message.segments} segment(s) ·{' '}
                                        {message.provider ?? 'not submitted'}
                                    </p>
                                </div>
                                <Badge
                                    variant={
                                        statusTone[message.status] ?? 'muted'
                                    }
                                >
                                    {message.status_label}
                                </Badge>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
