import { Head, Link, router, useForm } from '@inertiajs/react';
import { CheckCircle2, ClipboardCheck, Megaphone, ShieldX, Volume2 } from 'lucide-react';
import { useState } from 'react';
import EmptyState from '@/components/empty-state';
import PageHeader from '@/components/page-header';
import StatCard from '@/components/stat-card';
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
import { Textarea } from '@/components/ui/textarea';
import { urls } from '@/lib/urls';
import type {
    ApprovalAudioRow,
    ApprovalCampaignRow,
    ApprovalQueue,
    ApprovalSenderIdRow,
    SelectOption,
} from '@/types';

type PageProps = {
    queues: {
        campaigns: ApprovalQueue<ApprovalCampaignRow> | null;
        audio: ApprovalQueue<ApprovalAudioRow> | null;
        sender_ids: ApprovalQueue<ApprovalSenderIdRow> | null;
    };
    filters: { channel: string };
    campaignChannels: SelectOption[];
    senderChannels: SelectOption[];
    outboundCallerId: string | null;
    abilities: { campaigns: boolean; sender_ids: boolean };
};

type Tab = 'campaigns' | 'audio' | 'sender_ids';

type Decision = {
    kind: 'campaign' | 'audio' | 'sender';
    id: number;
    name: string;
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

export default function ApprovalsIndex({
    queues,
    filters,
    campaignChannels,
    senderChannels,
    outboundCallerId,
    abilities,
}: PageProps) {
    const [tab, setTab] = useState<Tab>(
        abilities.campaigns ? 'campaigns' : 'sender_ids',
    );
    const [decision, setDecision] = useState<Decision | null>(null);
    const rejectForm = useForm({ reason: '' });

    const filterChannels: SelectOption[] = [
        ...campaignChannels,
        ...senderChannels.filter(
            (channel) =>
                !campaignChannels.some(
                    (candidate) => candidate.value === channel.value,
                ),
        ),
    ];

    const tabs: { key: Tab; label: string; count: number }[] = [
        ...(abilities.campaigns
            ? [
                  {
                      key: 'campaigns' as Tab,
                      label: 'Campaigns',
                      count: queues.campaigns?.total ?? 0,
                  },
                  {
                      key: 'audio' as Tab,
                      label: 'Voice audio',
                      count: queues.audio?.total ?? 0,
                  },
              ]
            : []),
        ...(abilities.sender_ids
            ? [
                  {
                      key: 'sender_ids' as Tab,
                      label: 'Sender IDs',
                      count: queues.sender_ids?.total ?? 0,
                  },
              ]
            : []),
    ];

    const approve = (url: string, confirmation: string): void => {
        if (!window.confirm(confirmation)) {
            return;
        }

        router.post(url, {}, { preserveScroll: true });
    };

    const openReject = (kind: Decision['kind'], id: number, name: string): void => {
        rejectForm.reset();
        rejectForm.clearErrors();
        setDecision({ kind, id, name });
    };

    const rejectUrl = (target: Decision): string => {
        if (target.kind === 'campaign') {
            return urls.campaigns.reject(target.id);
        }

        if (target.kind === 'audio') {
            return urls.campaigns.rejectAudio(target.id);
        }

        return urls.senderIds.reject(target.id);
    };

    const confirmReject = (): void => {
        if (decision === null) {
            return;
        }

        rejectForm.post(rejectUrl(decision), {
            preserveScroll: true,
            onSuccess: () => {
                setDecision(null);
                rejectForm.reset();
            },
        });
    };

    const applyChannel = (value: string): void => {
        router.get(
            urls.approvals.index,
            value === 'all' ? {} : { channel: value },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Approvals" />

            <div className="flex flex-col gap-4">
                <PageHeader
                    icon={ClipboardCheck}
                    title="Approvals"
                    description="Campaigns, voice audio and sender IDs waiting on a platform decision."
                    actions={
                        <div className="w-48">
                            <Select
                                value={filters.channel || 'all'}
                                onValueChange={applyChannel}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="All channels" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All channels
                                    </SelectItem>
                                    {filterChannels.map((channel) => (
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
                    }
                />

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        title="Campaigns"
                        value={queues.campaigns?.total ?? 0}
                        description="Awaiting a decision"
                        icon={Megaphone}
                        tone="warning"
                    />
                    <StatCard
                        title="Voice audio"
                        value={queues.audio?.total ?? 0}
                        description="Recordings and scripts to clear"
                        icon={Volume2}
                        tone="signal"
                    />
                    <StatCard
                        title="Sender IDs"
                        value={queues.sender_ids?.total ?? 0}
                        description="Requests needing review"
                        icon={ShieldX}
                        tone="brand"
                    />
                    <StatCard
                        title="Outbound DID"
                        value={outboundCallerId ?? 'Not set'}
                        description="Shared by every voice campaign"
                        icon={Volume2}
                        tone={outboundCallerId ? 'success' : 'danger'}
                    />
                </div>

                <div className="bg-muted/50 flex flex-wrap gap-1 rounded-xl p-1">
                    {tabs.map((item) => (
                        <Button
                            key={item.key}
                            variant={tab === item.key ? 'default' : 'ghost'}
                            size="sm"
                            onClick={() => setTab(item.key)}
                        >
                            {item.label}
                            <span className="text-xs opacity-70">
                                {item.count}
                            </span>
                        </Button>
                    ))}
                </div>

                {tab === 'campaigns' && (
                    <Card className="py-4">
                        <CardHeader>
                            <CardTitle>Campaign review queue</CardTitle>
                            <CardDescription>
                                A campaign can only be approved once its channel
                                requirements are met.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="px-0">
                            {(queues.campaigns?.items.length ?? 0) === 0 ? (
                                <EmptyState
                                    icon={CheckCircle2}
                                    title="No campaigns waiting"
                                    description="Submitted campaigns land here for review."
                                />
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="pl-4">Campaign</TableHead>
                                            <TableHead>Channel</TableHead>
                                            <TableHead>Sender</TableHead>
                                            <TableHead>Recipients</TableHead>
                                            <TableHead>Estimate</TableHead>
                                            <TableHead>Blockers</TableHead>
                                            <TableHead>Submitted</TableHead>
                                            <TableHead className="pr-4 text-right">Decision</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
{queues.campaigns?.items.map((campaign) => (
    <TableRow key={campaign.id}>
        <TableCell className="pl-4">
            <Link
                href={urls.campaigns.show(campaign.id)}
                className="font-medium hover:underline"
            >
                {campaign.name}
            </Link>
            <span className="text-muted-foreground block text-xs">
                {campaign.reference} · {campaign.client ?? 'Platform'}
            </span>
        </TableCell>
        <TableCell>{campaign.channel_label}</TableCell>
        <TableCell>{campaign.sender ?? '—'}</TableCell>
        <TableCell className="tabular-nums">{campaign.recipients}</TableCell>
        <TableCell className="tabular-nums">{money(campaign.estimated_cost)}</TableCell>
        <TableCell>
            {campaign.blockers.length === 0 ? (
                <StatusBadge status="approved" label="Ready" />
            ) : (
                <div className="flex flex-wrap gap-1">
                    {campaign.blockers.map((blocker) => (
                        <StatusBadge key={blocker} status="rejected" label={blocker} />
                    ))}
                </div>
            )}
        </TableCell>
        <TableCell>{moment(campaign.submitted_at)}</TableCell>
        <TableCell className="pr-4">
            <div className="flex justify-end gap-2">
                <Button
                    size="sm"
                    disabled={campaign.blockers.length > 0}
                    onClick={() =>
                        approve(
                            urls.campaigns.approve(campaign.id),
                            `Approve ${campaign.name}?`,
                        )
                    }
                >
                    Approve
                </Button>
                <Button
                    size="sm"
                    variant="outline"
                    onClick={() => openReject('campaign', campaign.id, campaign.name)}
                >
                    Reject
                </Button>
            </div>
        </TableCell>
    </TableRow>
))}
                                    </TableBody>
                                </Table>
                            )}
                        </CardContent>
                    </Card>
                )}

                {tab === 'audio' && (
                    <Card className="py-4">
                        <CardHeader>
                            <CardTitle>Voice audio queue</CardTitle>
                            <CardDescription>
                                Every voice campaign plays one approved recording
                                or script from the platform DID.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="px-0">
                            {(queues.audio?.items.length ?? 0) === 0 ? (
                                <EmptyState
                                    icon={Volume2}
                                    title="No audio waiting"
                                    description="Voice campaigns submitted for review appear here."
                                />
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="pl-4">Campaign</TableHead>
                                            <TableHead>Audio</TableHead>
                                            <TableHead>Duration</TableHead>
                                            <TableHead>Other blockers</TableHead>
                                            <TableHead>Submitted</TableHead>
                                            <TableHead className="pr-4 text-right">Decision</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
{queues.audio?.items.map((campaign) => (
    <TableRow key={campaign.id}>
        <TableCell className="pl-4">
            <Link
                href={urls.campaigns.show(campaign.id)}
                className="font-medium hover:underline"
            >
                {campaign.name}
            </Link>
            <span className="text-muted-foreground block text-xs">
                {campaign.reference} · {campaign.client ?? 'Platform'}
            </span>
        </TableCell>
        <TableCell className="max-w-xs">
            {campaign.voice_recording_path ? (
                <span className="font-mono text-xs break-all">
                    {campaign.voice_recording_path}
                </span>
            ) : (
                <span className="text-sm whitespace-pre-wrap">
                    {campaign.voice_script ?? 'No audio captured'}
                </span>
            )}
        </TableCell>
        <TableCell className="tabular-nums">
            {campaign.voice_max_duration_seconds}s
        </TableCell>
        <TableCell>
            {campaign.blockers.length === 0 ? (
                <span className="text-muted-foreground text-sm">—</span>
            ) : (
                <div className="flex flex-wrap gap-1">
                    {campaign.blockers.map((blocker) => (
                        <StatusBadge key={blocker} status="rejected" label={blocker} />
                    ))}
                </div>
            )}
        </TableCell>
        <TableCell>{moment(campaign.submitted_at)}</TableCell>
        <TableCell className="pr-4">
            <div className="flex justify-end gap-2">
                <Button
                    size="sm"
                    onClick={() =>
                        approve(
                            urls.campaigns.approveAudio(campaign.id),
                            `Approve the audio for ${campaign.name}?`,
                        )
                    }
                >
                    Approve audio
                </Button>
                <Button
                    size="sm"
                    variant="outline"
                    onClick={() => openReject('audio', campaign.id, campaign.name)}
                >
                    Reject
                </Button>
            </div>
        </TableCell>
    </TableRow>
))}
                                    </TableBody>
                                </Table>
                            )}
                        </CardContent>
                    </Card>
                )}

                {tab === 'sender_ids' && (
                    <Card className="py-4">
                        <CardHeader>
                            <CardTitle>Sender ID review queue</CardTitle>
                            <CardDescription>
                                SMS brands, email from addresses and voice caller
                                numbers waiting for verification.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="px-0">
                            {(queues.sender_ids?.items.length ?? 0) === 0 ? (
                                <EmptyState
                                    icon={ShieldX}
                                    title="No sender IDs waiting"
                                    description="Tenant requests land here for review."
                                />
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="pl-4">Identity</TableHead>
                                            <TableHead>Channel</TableHead>
                                            <TableHead>Client</TableHead>
                                            <TableHead>Requester</TableHead>
                                            <TableHead>Requested</TableHead>
                                            <TableHead className="pr-4 text-right">Decision</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
{queues.sender_ids?.items.map((senderId) => (
    <TableRow key={senderId.id}>
        <TableCell className="pl-4">
            <span className="font-medium">{senderId.name}</span>
            <span className="text-muted-foreground block text-xs">
                {senderId.identifier_label}
                {senderId.country ? ` · ${senderId.country}` : ''}
            </span>
        </TableCell>
        <TableCell>{senderId.channel_label}</TableCell>
        <TableCell>{senderId.client ?? 'Platform'}</TableCell>
        <TableCell>{senderId.requester ?? '—'}</TableCell>
        <TableCell>{moment(senderId.requested_at)}</TableCell>
        <TableCell className="pr-4">
            <div className="flex justify-end gap-2">
                <Button
                    size="sm"
                    onClick={() =>
                        approve(
                            urls.senderIds.approve(senderId.id),
                            `Approve ${senderId.name} for ${senderId.channel_label}?`,
                        )
                    }
                >
                    Approve
                </Button>
                <Button
                    size="sm"
                    variant="outline"
                    onClick={() => openReject('sender', senderId.id, senderId.name)}
                >
                    Reject
                </Button>
            </div>
        </TableCell>
    </TableRow>
))}
                                    </TableBody>
                                </Table>
                            )}
                        </CardContent>
                    </Card>
                )}
            </div>

            <Dialog
                open={decision !== null}
                onOpenChange={(open) => !open && setDecision(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Reject {decision?.name}</DialogTitle>
                        <DialogDescription>
                            The requester sees this reason and can fix the record
                            before submitting it again.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-1.5">
                        <Label htmlFor="approval-reason">Reason</Label>
                        <Textarea
                            id="approval-reason"
                            value={rejectForm.data.reason}
                            onChange={(event) =>
                                rejectForm.setData('reason', event.target.value)
                            }
                            rows={3}
                            placeholder="Explain what needs to change"
                        />
                        {rejectForm.errors.reason && (
                            <p className="text-destructive text-xs">
                                {rejectForm.errors.reason}
                            </p>
                        )}
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setDecision(null)}
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            disabled={
                                rejectForm.processing ||
                                rejectForm.data.reason.trim() === ''
                            }
                            onClick={confirmReject}
                        >
                            Reject
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

ApprovalsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Approvals',
            href: urls.approvals.index,
        },
    ],
};

