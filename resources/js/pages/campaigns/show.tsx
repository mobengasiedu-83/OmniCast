import { Head, Link, router } from '@inertiajs/react';
import { Megaphone } from 'lucide-react';
import { useState } from 'react';
import PageHeader from '@/components/page-header';
import Pagination from '@/components/pagination';
import StatusBadge from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { urls } from '@/lib/urls';
import type {
    CampaignDetail,
    CampaignRecipient,
    Paginated,
} from '@/types';

type Abilities = {
    update: boolean;
    submit: boolean;
    approve: boolean;
    launch: boolean;
    cancel: boolean;
    delete: boolean;
};

type PageProps = {
    campaign: CampaignDetail;
    statusCounts: Record<string, number>;
    recipients: Paginated<CampaignRecipient>;
    abilities: Abilities;
};

function money(value: number): string {
    return `GHS ${value.toLocaleString('en-GH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

function moment(value: string | null): string {
    return value
        ? new Date(value).toLocaleString(undefined, {
              dateStyle: 'medium',
              timeStyle: 'short',
          })
        : '—';
}

export default function CampaignsShow({
    campaign,
    statusCounts,
    recipients,
    abilities,
}: PageProps) {
    const [rejecting, setRejecting] = useState(false);
    const [reason, setReason] = useState('');

    const act = (url: string, confirmation?: string): void => {
        if (confirmation && !window.confirm(confirmation)) {
            return;
        }

        router.post(url, {}, { preserveScroll: true });
    };

    const reject = (): void => {
        router.post(
            urls.campaigns.reject(campaign.id),
            { reason },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setRejecting(false);
                    setReason('');
                },
            },
        );
    };

    const metrics = [
        {
            label: 'Recipients',
            value: campaign.estimated_recipients ?? '—',
        },
        {
            label: 'Estimated cost',
            value: money(campaign.estimated_cost),
        },
        {
            label: 'Reserved',
            value: money(campaign.reserved_amount),
        },
        {
            label: 'Actual spend',
            value: money(campaign.actual_cost),
        },
    ];

    return (
        <>
            <Head title={campaign.name} />

            <div className="flex flex-col gap-4">
                <PageHeader
                    icon={Megaphone}
                    title={campaign.name}
                    badge={
                        <StatusBadge
                            status={campaign.status}
                            label={campaign.status_label}
                        />
                    }
                    description={`${campaign.reference} · ${campaign.channel_label} · ${campaign.audience}`}
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                        {abilities.update && (
                            <Button variant="outline" asChild>
                                <Link href={urls.campaigns.edit(campaign.id)}>
                                    Edit
                                </Link>
                            </Button>
                        )}
                        {abilities.submit && (
                            <Button
                                onClick={() =>
                                    act(
                                        urls.campaigns.submit(campaign.id),
                                        'Submit this campaign for approval? The estimated cost will be reserved.',
                                    )
                                }
                            >
                                Submit for approval
                            </Button>
                        )}
                        {abilities.approve && (
                            <>
                                <Button
                                    onClick={() =>
                                        act(urls.campaigns.approve(campaign.id))
                                    }
                                >
                                    Approve
                                </Button>
                                <Button
                                    variant="outline"
                                    onClick={() => setRejecting(true)}
                                >
                                    Reject
                                </Button>
                            </>
                        )}
                        {abilities.launch && (
                            <Button
                                onClick={() =>
                                    act(
                                        urls.campaigns.launch(campaign.id),
                                        'Launch this campaign now?',
                                    )
                                }
                            >
                                Launch
                            </Button>
                        )}
                        {abilities.cancel && (
                            <Button
                                variant="outline"
                                onClick={() =>
                                    act(
                                        urls.campaigns.cancel(campaign.id),
                                        'Cancel this campaign and release its held funds?',
                                    )
                                }
                            >
                                Cancel
                            </Button>
                        )}
                        {abilities.delete && (
                            <Button
                                variant="destructive"
                                onClick={() => {
                                    if (
                                        window.confirm(
                                            'Delete this draft campaign?',
                                        )
                                    ) {
                                        router.delete(
                                            urls.campaigns.destroy(campaign.id),
                                        );
                                    }
                                }}
                            >
                                Delete
                            </Button>
                        )}
                        </div>
                    }
                />

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {metrics.map((metric, index) => (
                        <Card
                            key={metric.label}
                            className="card-lift animate-fade-in-up gap-1 py-4"
                            style={{ animationDelay: `${index * 60}ms` }}
                        >
                            <CardContent className="space-y-1 px-4">
                                <p className="text-muted-foreground text-[11px] font-semibold tracking-[0.14em] uppercase">
                                    {metric.label}
                                </p>
                                <p className="text-lg font-semibold tabular-nums">
                                    {metric.value}
                                </p>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <Card className="py-4 lg:col-span-2">
                        <CardHeader>
                            <CardTitle>Message</CardTitle>
                            <CardDescription>
                                Copy delivered to{' '}
                                {campaign.audience.toLowerCase()}.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            {campaign.sms_message && (
                                <div className="space-y-1.5">
                                    <p className="text-muted-foreground text-[11px] font-semibold tracking-[0.14em] uppercase">
                                        SMS
                                    </p>
                                    <p className="bg-signal-soft/70 ring-signal/25 max-w-md rounded-2xl rounded-tl-sm p-3 text-sm whitespace-pre-wrap ring-1">
                                        {campaign.sms_message}
                                    </p>
                                </div>
                            )}
                            {campaign.voice_script && (
                                <div className="space-y-1.5">
                                    <p className="text-muted-foreground text-[11px] font-semibold tracking-[0.14em] uppercase">
                                        Voice script
                                    </p>
                                    <p className="bg-brand-soft/70 ring-brand/20 max-w-md rounded-2xl rounded-tl-sm p-3 text-sm whitespace-pre-wrap ring-1">
                                        {campaign.voice_script}
                                    </p>
                                </div>
                            )}
                            {!campaign.sms_message && !campaign.voice_script && (
                                <p className="text-muted-foreground text-sm">
                                    No copy captured yet.
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    <Card className="py-4">
                        <CardHeader>
                            <CardTitle>Delivery</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-2 text-sm">
                            {Object.keys(statusCounts).length > 0 && (
                                <div className="mb-1 flex flex-wrap gap-1.5">
                                    {Object.entries(statusCounts).map(
                                        ([status, count]) => (
                                            <StatusBadge
                                                key={status}
                                                status={status}
                                                label={`${status.replace(/_/g, ' ')} · ${count}`}
                                            />
                                        ),
                                    )}
                                </div>
                            )}

                            <div className="flex justify-between gap-3">
                                <span className="text-muted-foreground">
                                    Sender ID
                                </span>
                                <span>{campaign.sender ?? '—'}</span>
                            </div>
                            <div className="flex justify-between gap-3">
                                <span className="text-muted-foreground">
                                    Caller ID
                                </span>
                                <span>{campaign.caller ?? '—'}</span>
                            </div>
                            <div className="flex justify-between gap-3">
                                <span className="text-muted-foreground">
                                    Scheduled
                                </span>
                                <span>{moment(campaign.scheduled_at)}</span>
                            </div>
                            <div className="flex justify-between gap-3">
                                <span className="text-muted-foreground">
                                    Launched
                                </span>
                                <span>{moment(campaign.launched_at)}</span>
                            </div>
                            <div className="flex justify-between gap-3">
                                <span className="text-muted-foreground">
                                    Completed
                                </span>
                                <span>{moment(campaign.completed_at)}</span>
                            </div>
                            <div className="flex justify-between gap-3">
                                <span className="text-muted-foreground">
                                    Created by
                                </span>
                                <span>{campaign.creator ?? '—'}</span>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <Card className="py-4">
                    <CardHeader>
                        <CardTitle>Recipients</CardTitle>
                        <CardDescription>
                            {recipients.total} recipient
                            {recipients.total === 1 ? '' : 's'} on this campaign.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="px-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="pl-4">Contact</TableHead>
                                    <TableHead>Phone</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Attempts</TableHead>
                                    <TableHead>Sent</TableHead>
                                    <TableHead className="pr-4">Error</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {recipients.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={6}
                                            className="text-muted-foreground py-6 text-center"
                                        >
                                            Recipients appear once the campaign
                                            is launched.
                                        </TableCell>
                                    </TableRow>
                                )}
                                {recipients.data.map((recipient) => (
                                    <TableRow key={recipient.id}>
                                        <TableCell className="pl-4">
                                            <span className="font-medium">
                                                {recipient.name ?? 'Unknown'}
                                            </span>
                                            <span className="text-muted-foreground block text-xs">
                                                {recipient.phone ?? '—'}
                                            </span>
                                        </TableCell>
                                        <TableCell>
                                            {recipient.phone ?? '—'}
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge
                                                status={recipient.status}
                                                label={recipient.status_label}
                                            />
                                        </TableCell>
                                        <TableCell>
                                            {recipient.attempts}
                                        </TableCell>
                                        <TableCell>
                                            {moment(recipient.sent_at)}
                                        </TableCell>
                                        <TableCell className="pr-4">
                                            {recipient.error_message ?? '—'}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                    {recipients.last_page > 1 && (
                        <div className="px-4 pb-2">
                            <Pagination paginator={recipients} />
                        </div>
                    )}
                </Card>
            </div>
            <Dialog open={rejecting} onOpenChange={setRejecting}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Reject campaign</DialogTitle>
                        <DialogDescription>
                            The submitter sees this reason and any held funds
                            are released.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-1.5">
                        <Label htmlFor="reason">Reason</Label>
                        <Textarea
                            id="reason"
                            value={reason}
                            onChange={(event) => setReason(event.target.value)}
                            rows={3}
                            placeholder="Explain what needs to change"
                        />
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setRejecting(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            disabled={reason.trim() === ''}
                            onClick={reject}
                        >
                            Reject campaign
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

CampaignsShow.layout = {
    breadcrumbs: [
        {
            title: 'Campaigns',
            href: urls.campaigns.index,
        },
        {
            title: 'Campaign detail',
            href: urls.campaigns.index,
        },
    ],
};
