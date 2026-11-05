import { Head, router, useForm } from '@inertiajs/react';
import { BarChart3, Plus, ShieldBan, UserX, Users } from 'lucide-react';
import { type FormEvent } from 'react';
import ClientFilter from '@/components/client-filter';
import EmptyState from '@/components/empty-state';
import PageHeader from '@/components/page-header';
import StatCard from '@/components/stat-card';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { urls } from '@/lib/urls';
import type {
    ClientOption,
    ContactQualityReport,
    ContactSuppressionRow,
    SelectOption,
} from '@/types';

type PageProps = {
    quality: ContactQualityReport;
    suppressions: ContactSuppressionRow[];
    channels: SelectOption[];
    reasons: SelectOption[];
    canManageGlobalSuppressions: boolean;
    clients: ClientOption[];
    clientId: number;
};

/** Render an ISO timestamp the way the rest of the workspace does. */
const formatDateTime = (value: string | null): string =>
    value ? new Date(value).toLocaleString() : '—';

export default function ContactDndRegistry({
    quality,
    suppressions,
    channels,
    reasons,
    canManageGlobalSuppressions,
    clients,
    clientId,
}: PageProps) {
    const suppressionForm = useForm({
        channel: 'sms',
        identifier: '',
        reason: 'manual',
        global: false,
    });

    const submitSuppression = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        suppressionForm.post(urls.contacts.storeSuppression, {
            preserveScroll: true,
            onSuccess: () => suppressionForm.reset('identifier'),
        });
    };

    return (
        <>
            <Head title="DND" />

            <div className="flex flex-col gap-4">
                <PageHeader
                    icon={ShieldBan}
                    title="DND & suppression registry"
                    description="Numbers and addresses that must never receive a broadcast. Entries covering every channel are the DND list."
                    actions={
                        <ClientFilter
                            clients={clients}
                            value={clientId}
                            query={{}}
                        />
                    }
                />

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        title="On the DND list"
                        value={quality.dnd}
                        description="Blocked from every audience"
                        icon={UserX}
                        tone={quality.dnd > 0 ? 'warning' : 'success'}
                    />
                    <StatCard
                        title="Registry entries"
                        value={quality.suppressed}
                        description="Active DND and suppression rows"
                        icon={ShieldBan}
                        tone="neutral"
                    />
                    <StatCard
                        title="Broadcastable"
                        value={quality.broadcastable}
                        description={`${quality.total} contacts in the book`}
                        icon={BarChart3}
                        tone="success"
                    />
                    <StatCard
                        title="Contacts"
                        value={quality.total}
                        description="Everyone reachable across channels"
                        icon={Users}
                        tone="brand"
                    />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-sm font-medium">
                            <BarChart3 className="text-brand size-4" />
                            Channel reach
                        </CardTitle>
                        <CardDescription>
                            Contacts that can be reached right now, compared
                            with the numbers blocked by DND.
                        </CardDescription>
                    </CardHeader>

                    <CardContent className="grid gap-3">
                        {quality.channels.map((channel) => {
                            const share =
                                quality.total > 0
                                    ? Math.round(
                                          (channel.eligible / quality.total) *
                                              100,
                                      )
                                    : 0;

                            return (
                                <div
                                    key={channel.channel}
                                    className="flex items-center justify-between gap-3 rounded-lg border border-border/60 px-3 py-2"
                                >
                                    <div>
                                        <p className="text-sm font-medium uppercase">
                                            {channel.channel}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {channel.eligible} eligible ({share}
                                            %)
                                        </p>
                                    </div>
                                    <Badge
                                        variant={
                                            channel.blocked > 0
                                                ? 'warning'
                                                : 'success'
                                        }
                                    >
                                        {channel.blocked} blocked
                                    </Badge>
                                </div>
                            );
                        })}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-sm font-medium">
                            <ShieldBan className="text-brand size-4" />
                            DND & suppression registry
                        </CardTitle>
                        <CardDescription>
                            Numbers and addresses that must never receive a
                            broadcast. "Every channel" entries are the DND list.
                        </CardDescription>
                    </CardHeader>

                    <CardContent className="flex flex-col gap-4">
                        <form
                            onSubmit={submitSuppression}
                            className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
                        >
                            <div className="grid gap-1.5">
                                <Label>Channel</Label>
                                <Select
                                    value={suppressionForm.data.channel}
                                    onValueChange={(value) =>
                                        suppressionForm.setData(
                                            'channel',
                                            value,
                                        )
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {channels.map((channel) => (
                                            <SelectItem
                                                key={channel.value}
                                                value={channel.value}
                                            >
                                                {channel.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="suppression-identifier">
                                    Number or email
                                </Label>
                                <Input
                                    id="suppression-identifier"
                                    value={suppressionForm.data.identifier}
                                    onChange={(event) =>
                                        suppressionForm.setData(
                                            'identifier',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="+233201234567"
                                    required
                                />
                                {suppressionForm.errors.identifier && (
                                    <p className="text-xs text-destructive">
                                        {suppressionForm.errors.identifier}
                                    </p>
                                )}
                            </div>

                            <div className="grid gap-1.5">
                                <Label>Reason</Label>
                                <Select
                                    value={suppressionForm.data.reason}
                                    onValueChange={(value) =>
                                        suppressionForm.setData('reason', value)
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {reasons.map((reason) => (
                                            <SelectItem
                                                key={reason.value}
                                                value={reason.value}
                                            >
                                                {reason.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="flex items-end">
                                <Button
                                    type="submit"
                                    disabled={suppressionForm.processing}
                                >
                                    <Plus />
                                    Suppress
                                </Button>
                            </div>

                            {canManageGlobalSuppressions && (
                                <label className="flex items-center gap-2 text-sm sm:col-span-2">
                                    <Checkbox
                                        checked={suppressionForm.data.global}
                                        onCheckedChange={(checked) =>
                                            suppressionForm.setData(
                                                'global',
                                                checked === true,
                                            )
                                        }
                                    />
                                    Apply across every client
                                </label>
                            )}
                        </form>

                        {suppressions.length === 0 ? (
                            <EmptyState
                                icon={ShieldBan}
                                title="Registry is empty"
                                description="No numbers or addresses are currently suppressed."
                            />
                        ) : (
                            <div className="flex flex-col divide-y divide-border/60">
                                {suppressions.map((suppression) => (
                                    <div
                                        key={suppression.id}
                                        className="flex flex-wrap items-center justify-between gap-2 py-2 first:pt-0 last:pb-0"
                                    >
                                        <div className="space-y-0.5">
                                            <p className="flex items-center gap-2 text-sm font-medium">
                                                <span className="uppercase">
                                                    {suppression.channel}
                                                </span>
                                                <span className="font-mono text-xs">
                                                    {suppression.identifier}
                                                </span>
                                                {suppression.is_global && (
                                                    <Badge variant="warning">
                                                        Global
                                                    </Badge>
                                                )}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {suppression.reason_label} ·{' '}
                                                {suppression.source} ·{' '}
                                                {formatDateTime(
                                                    suppression.suppressed_at,
                                                )}
                                            </p>
                                        </div>

                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() =>
                                                router.delete(
                                                    urls.contacts.liftSuppression(
                                                        suppression.id,
                                                    ),
                                                    {
                                                        preserveScroll: true,
                                                    },
                                                )
                                            }
                                        >
                                            Lift
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

ContactDndRegistry.layout = {
    breadcrumbs: [
        {
            title: 'Contacts',
            href: urls.contacts.index,
        },
        {
            title: 'DND',
            href: urls.contacts.dndRegistry,
        },
    ],
};
