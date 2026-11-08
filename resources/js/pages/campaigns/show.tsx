import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    CheckCircle2,
    Mail,
    Megaphone,
    MessageSquareText,
    Paperclip,
    PhoneCall,
} from 'lucide-react';
import { useState } from 'react';
import { useConfirm, type ConfirmOptions } from '@/components/confirm-dialog';
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
    CampaignReview,
    CampaignVersionRow,
    EmailMessageReport,
    Paginated,
    SmsMessageReport,
    VoiceCallReport,
} from '@/types';

type Abilities = {
    update: boolean;
    submit: boolean;
    approve: boolean;
    review_audio: boolean;
    launch: boolean;
    cancel: boolean;
    delete: boolean;
    duplicate: boolean;
};

type PageProps = {
    campaign: CampaignDetail;
    statusCounts: Record<string, number>;
    versions: CampaignVersionRow[];
    recipients: Paginated<CampaignRecipient>;
    review: CampaignReview;
    voiceReport: VoiceCallReport | null;
    smsReport: SmsMessageReport | null;
    emailReport: EmailMessageReport | null;
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
    versions,
    recipients,
    review,
    voiceReport,
    smsReport,
    emailReport,
    abilities,
}: PageProps) {
    const [rejecting, setRejecting] = useState(false);
    const [rejectKind, setRejectKind] = useState<'campaign' | 'audio'>(
        'campaign',
    );
    const [reason, setReason] = useState('');
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    const confirm = useConfirm();

    const act = async (
        url: string,
        confirmation?: ConfirmOptions,
    ): Promise<void> => {
        if (confirmation && !(await confirm(confirmation))) {
            return;
        }

        router.post(url, {}, { preserveScroll: true });
    };

    const remove = async (): Promise<void> => {
        const confirmed = await confirm({
            title: 'Delete this draft campaign?',
            description:
                'The campaign leaves the workspace and its audience snapshot is dropped.',
            confirmLabel: 'Delete campaign',
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        router.delete(urls.campaigns.destroy(campaign.id));
    };

    const openReject = (kind: 'campaign' | 'audio'): void => {
        setRejectKind(kind);
        setReason('');
        setRejecting(true);
    };

    const reject = (): void => {
        const url =
            rejectKind === 'audio'
                ? urls.campaigns.rejectAudio(campaign.id)
                : urls.campaigns.reject(campaign.id);

        router.post(
            url,
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
                            {abilities.duplicate && (
                                <Button
                                    variant="outline"
                                    onClick={() =>
                                        void act(
                                            urls.campaigns.duplicate(
                                                campaign.id,
                                            ),
                                            {
                                                title: 'Duplicate this campaign?',
                                                description:
                                                    'A new draft is created with the same audience and copy so you can change it before sending.',
                                                confirmLabel:
                                                    'Duplicate campaign',
                                            },
                                        )
                                    }
                                >
                                    Duplicate
                                </Button>
                            )}
                            {abilities.update && (
                                <Button variant="outline" asChild>
                                    <Link
                                        href={urls.campaigns.edit(campaign.id)}
                                    >
                                        Edit
                                    </Link>
                                </Button>
                            )}
                            {abilities.submit && (
                                <Button
                                    onClick={() =>
                                        void act(
                                            urls.campaigns.submit(campaign.id),
                                            review.requires_campaign_approval
                                                ? {
                                                      title: 'Submit this campaign for approval?',
                                                      description:
                                                          'The estimated cost is reserved now and stays held until a reviewer approves it.',
                                                      confirmLabel:
                                                          'Submit for approval',
                                                  }
                                                : {
                                                      title: 'Submit this campaign?',
                                                      description:
                                                          'The estimated cost is reserved now and delivery starts once every asset is approved.',
                                                      confirmLabel:
                                                          'Submit campaign',
                                                  },
                                        )
                                    }
                                >
                                    {review.requires_campaign_approval
                                        ? 'Submit for approval'
                                        : 'Submit campaign'}
                                </Button>
                            )}
                            {abilities.approve && (
                                <>
                                    {review.requires_campaign_approval && (
                                        <Button
                                            onClick={() =>
                                                void act(
                                                    urls.campaigns.approve(
                                                        campaign.id,
                                                    ),
                                                )
                                            }
                                        >
                                            Approve
                                        </Button>
                                    )}
                                    <Button
                                        variant="outline"
                                        onClick={() => openReject('campaign')}
                                    >
                                        Reject
                                    </Button>
                                </>
                            )}
                            {abilities.review_audio && (
                                <>
                                    <Button
                                        variant="outline"
                                        onClick={() =>
                                            void act(
                                                urls.campaigns.approveAudio(
                                                    campaign.id,
                                                ),
                                                {
                                                    title: 'Approve the voice audio?',
                                                    description:
                                                        'Voice delivery unblocks as soon as this audio is approved.',
                                                    confirmLabel:
                                                        'Approve audio',
                                                },
                                            )
                                        }
                                    >
                                        Approve audio
                                    </Button>
                                    <Button
                                        variant="outline"
                                        onClick={() => openReject('audio')}
                                    >
                                        Reject audio
                                    </Button>
                                </>
                            )}
                            {abilities.launch && (
                                <Button
                                    onClick={() =>
                                        void act(
                                            urls.campaigns.launch(campaign.id),
                                            {
                                                title: 'Launch this campaign now?',
                                                description:
                                                    'Delivery starts immediately and each message sent is charged to the wallet.',
                                                confirmLabel: 'Launch campaign',
                                            },
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
                                        void act(
                                            urls.campaigns.cancel(campaign.id),
                                            {
                                                title: 'Cancel this campaign?',
                                                description:
                                                    'Held funds are released back to the wallet and nothing is sent.',
                                                confirmLabel: 'Cancel campaign',
                                                tone: 'danger',
                                            },
                                        )
                                    }
                                >
                                    Cancel
                                </Button>
                            )}
                            {abilities.delete && (
                                <Button
                                    variant="destructive"
                                    onClick={() => void remove()}
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
                                <p className="text-[11px] font-semibold tracking-[0.14em] text-muted-foreground uppercase">
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
                                    <p className="text-[11px] font-semibold tracking-[0.14em] text-muted-foreground uppercase">
                                        SMS
                                    </p>
                                    <p className="max-w-md rounded-2xl rounded-tl-sm bg-signal-soft/70 p-3 text-sm whitespace-pre-wrap ring-1 ring-signal/25">
                                        {campaign.sms_message}
                                    </p>
                                </div>
                            )}
                            {campaign.email_subject && (
                                <div className="space-y-1.5">
                                    <p className="text-[11px] font-semibold tracking-[0.14em] text-muted-foreground uppercase">
                                        Email subject
                                    </p>
                                    <p className="text-sm font-medium">
                                        {campaign.email_subject}
                                    </p>
                                </div>
                            )}
                            {campaign.email_body && (
                                <div className="space-y-1.5">
                                    <p className="text-[11px] font-semibold tracking-[0.14em] text-muted-foreground uppercase">
                                        Email body ·{' '}
                                        {campaign.email_format === 'text'
                                            ? 'plain text'
                                            : 'HTML'}
                                    </p>
                                    {campaign.email_format === 'text' ? (
                                        <p className="max-w-md rounded-2xl rounded-tl-sm bg-brand-soft/70 p-3 text-sm whitespace-pre-wrap ring-1 ring-brand/20">
                                            {campaign.email_body}
                                        </p>
                                    ) : (
                                        <iframe
                                            title="Email body preview"
                                            sandbox=""
                                            srcDoc={campaign.email_body}
                                            className="h-64 w-full max-w-md rounded-2xl ring-1 ring-border"
                                        />
                                    )}
                                </div>
                            )}
                            {campaign.email_attachments.length > 0 && (
                                <div className="space-y-1.5">
                                    <p className="text-[11px] font-semibold tracking-[0.14em] text-muted-foreground uppercase">
                                        Attachments
                                    </p>
                                    <ul className="flex flex-wrap gap-1.5">
                                        {campaign.email_attachments.map(
                                            (attachment) => (
                                                <li key={attachment.id}>
                                                    <a
                                                        href={
                                                            attachment.download_url
                                                        }
                                                        className="inline-flex items-center gap-1.5 rounded-md bg-muted px-2 py-1 text-xs text-muted-foreground transition-colors hover:text-foreground"
                                                    >
                                                        <Paperclip className="size-3.5" />
                                                        {attachment.name}
                                                        <span className="text-[11px] tabular-nums">
                                                            {attachment.size}
                                                        </span>
                                                    </a>
                                                </li>
                                            ),
                                        )}
                                    </ul>
                                </div>
                            )}
                            {campaign.voice_script && (
                                <div className="space-y-1.5">
                                    <p className="text-[11px] font-semibold tracking-[0.14em] text-muted-foreground uppercase">
                                        Voice script
                                    </p>
                                    <p className="max-w-md rounded-2xl rounded-tl-sm bg-brand-soft/70 p-3 text-sm whitespace-pre-wrap ring-1 ring-brand/20">
                                        {campaign.voice_script}
                                    </p>
                                </div>
                            )}
                            {!campaign.sms_message &&
                                !campaign.email_body &&
                                !campaign.voice_script && (
                                    <p className="text-sm text-muted-foreground">
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
                        <CardTitle>Approval readiness</CardTitle>
                        <CardDescription>
                            SMS campaigns run only under an approved sender ID.
                            Voice campaigns share one outbound DID and run once
                            the audio is approved.
                            {review.requires_campaign_approval
                                ? ' A reviewer also signs the campaign off before it launches.'
                                : ' Campaigns need no separate review and launch as soon as these gates clear.'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3 text-sm">
                        {errors.campaign && (
                            <p className="rounded-lg bg-destructive-soft p-2 text-xs text-destructive">
                                {errors.campaign}
                            </p>
                        )}
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <span className="text-muted-foreground">
                                Campaign review
                            </span>
                            <span className="text-xs font-medium">
                                {review.requires_campaign_approval
                                    ? 'Reviewer approval required'
                                    : 'Automatic once the assets are approved'}
                            </span>
                        </div>
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <span className="text-muted-foreground">
                                Outbound caller ID
                            </span>
                            <span className="font-mono text-xs">
                                {review.outbound_caller ?? 'Not configured'}
                            </span>
                        </div>
                        {review.requires_audio && (
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <span className="text-muted-foreground">
                                    Voice audio
                                </span>
                                <div className="flex items-center gap-2">
                                    <StatusBadge
                                        status={review.audio_status}
                                        label={review.audio_status_label}
                                    />
                                    <span className="text-xs text-muted-foreground">
                                        {review.audio_reviewer
                                            ? `${review.audio_reviewer} · ${moment(review.audio_reviewed_at)}`
                                            : 'Awaiting a reviewer'}
                                    </span>
                                </div>
                            </div>
                        )}
                        {review.audio_rejection_reason && (
                            <p className="rounded-lg bg-destructive-soft p-2 text-xs text-destructive">
                                {review.audio_rejection_reason}
                            </p>
                        )}
                        {review.blockers.length === 0 ? (
                            <p className="flex items-center gap-2 text-sm text-success">
                                <CheckCircle2 className="size-4" />
                                Every launch gate is cleared.
                            </p>
                        ) : (
                            <ul className="grid gap-2">
                                {review.blockers.map((blocker) => (
                                    <li
                                        key={blocker.key}
                                        className="rounded-lg bg-warning-soft/60 p-2 ring-1 ring-warning/25"
                                    >
                                        <p className="text-xs font-semibold text-warning-foreground dark:text-warning">
                                            {blocker.label}
                                        </p>
                                        <p className="mt-0.5 text-xs text-muted-foreground">
                                            {blocker.detail}
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>

                {voiceReport !== null && (
                    <Card className="py-4">
                        <CardHeader>
                            <CardTitle>Voice calls</CardTitle>
                            <CardDescription>
                                Answer rate, talk time and key presses come from
                                the callbacks the switch posts back.{' '}
                                <Link
                                    href={`${urls.reports.voice}?campaign=${campaign.id}`}
                                    className="text-brand underline-offset-4 hover:underline"
                                >
                                    Open the call report
                                </Link>
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                <div className="rounded-lg border p-3">
                                    <p className="text-xs text-muted-foreground">
                                        Dialled
                                    </p>
                                    <p className="text-xl font-semibold tabular-nums">
                                        {voiceReport.totals.calls.toLocaleString()}
                                    </p>
                                </div>
                                <div className="rounded-lg border p-3">
                                    <p className="text-xs text-muted-foreground">
                                        Answer rate
                                    </p>
                                    <p className="text-xl font-semibold tabular-nums">
                                        {voiceReport.totals.answer_rate === null
                                            ? '—'
                                            : `${voiceReport.totals.answer_rate}%`}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {voiceReport.totals.answered.toLocaleString()}{' '}
                                        answered
                                    </p>
                                </div>
                                <div className="rounded-lg border p-3">
                                    <p className="text-xs text-muted-foreground">
                                        Average talk time
                                    </p>
                                    <p className="text-xl font-semibold tabular-nums">
                                        {voiceReport.totals.average_duration > 0
                                            ? `${Math.floor(
                                                  voiceReport.totals
                                                      .average_duration / 60,
                                              )}:${String(
                                                  voiceReport.totals
                                                      .average_duration % 60,
                                              ).padStart(2, '0')}`
                                            : '—'}
                                    </p>
                                </div>
                                <div className="rounded-lg border p-3">
                                    <p className="text-xs text-muted-foreground">
                                        Opt-outs
                                    </p>
                                    <p className="text-xl font-semibold tabular-nums">
                                        {voiceReport.totals.opt_outs.toLocaleString()}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        Pressed the opt-out key
                                    </p>
                                </div>
                            </div>

                            {voiceReport.digits.length > 0 && (
                                <div className="flex flex-wrap gap-2">
                                    {voiceReport.digits.map((digit) => (
                                        <span
                                            key={`${digit.digit}-${digit.action}`}
                                            className="rounded-full border px-3 py-1 text-xs"
                                        >
                                            <span className="font-mono font-semibold">
                                                {digit.digit ?? '?'}
                                            </span>{' '}
                                            {digit.action_label ?? 'Unmapped'} ·{' '}
                                            {digit.total}
                                        </span>
                                    ))}
                                </div>
                            )}

                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Recipient</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Attempt</TableHead>
                                        <TableHead>Talk time</TableHead>
                                        <TableHead>Key press</TableHead>
                                        <TableHead className="pr-4">
                                            Dialled
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {voiceReport.recent.length === 0 && (
                                        <TableRow>
                                            <TableCell
                                                colSpan={6}
                                                className="py-6 text-center text-muted-foreground"
                                            >
                                                <span className="inline-flex items-center gap-2">
                                                    <PhoneCall className="size-4" />
                                                    No calls have been dialled
                                                    for this campaign yet.
                                                </span>
                                            </TableCell>
                                        </TableRow>
                                    )}
                                    {voiceReport.recent.map((call) => (
                                        <TableRow key={call.id}>
                                            <TableCell>
                                                <p className="font-medium">
                                                    {call.contact ?? call.to}
                                                </p>
                                                <p className="font-mono text-xs text-muted-foreground">
                                                    {call.to}
                                                </p>
                                            </TableCell>
                                            <TableCell>
                                                <StatusBadge
                                                    status={call.status}
                                                    label={call.status_label}
                                                />
                                            </TableCell>
                                            <TableCell>
                                                {call.attempts}
                                            </TableCell>
                                            <TableCell>
                                                {call.duration_seconds > 0
                                                    ? `${Math.floor(
                                                          call.duration_seconds /
                                                              60,
                                                      )}:${String(
                                                          call.duration_seconds %
                                                              60,
                                                      ).padStart(2, '0')}`
                                                    : '—'}
                                            </TableCell>
                                            <TableCell>
                                                {call.dtmf_digits
                                                    ? `${call.dtmf_digits} · ${
                                                          call.ivr_action_label ??
                                                          'Unmapped'
                                                      }`
                                                    : '—'}
                                            </TableCell>
                                            <TableCell className="pr-4">
                                                {moment(call.dialled_at)}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                )}

                {smsReport !== null && (
                    <Card className="py-4">
                        <CardHeader>
                            <CardTitle>SMS delivery</CardTitle>
                            <CardDescription>
                                Delivery rate and carrier status come from the
                                receipts the network posts back.{' '}
                                <Link
                                    href={`${urls.reports.sms}?campaign=${campaign.id}`}
                                    className="text-brand underline-offset-4 hover:underline"
                                >
                                    Open the delivery report
                                </Link>
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                <div className="rounded-lg border p-3">
                                    <p className="text-xs text-muted-foreground">
                                        Submitted
                                    </p>
                                    <p className="text-xl font-semibold tabular-nums">
                                        {smsReport.totals.messages.toLocaleString()}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {smsReport.totals.segments.toLocaleString()}{' '}
                                        segments
                                    </p>
                                </div>
                                <div className="rounded-lg border p-3">
                                    <p className="text-xs text-muted-foreground">
                                        Delivery rate
                                    </p>
                                    <p className="text-xl font-semibold tabular-nums">
                                        {smsReport.totals.delivery_rate === null
                                            ? '—'
                                            : `${smsReport.totals.delivery_rate}%`}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {smsReport.totals.delivered.toLocaleString()}{' '}
                                        delivered
                                    </p>
                                </div>
                                <div className="rounded-lg border p-3">
                                    <p className="text-xs text-muted-foreground">
                                        Awaiting receipts
                                    </p>
                                    <p className="text-xl font-semibold tabular-nums">
                                        {smsReport.totals.pending.toLocaleString()}
                                    </p>
                                </div>
                                <div className="rounded-lg border p-3">
                                    <p className="text-xs text-muted-foreground">
                                        Not delivered
                                    </p>
                                    <p className="text-xl font-semibold tabular-nums">
                                        {(
                                            smsReport.totals.undelivered +
                                            smsReport.totals.expired +
                                            smsReport.totals.rejected +
                                            smsReport.totals.failed
                                        ).toLocaleString()}
                                    </p>
                                </div>
                            </div>

                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Recipient</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Carrier</TableHead>
                                        <TableHead>Segments</TableHead>
                                        <TableHead className="pr-4">
                                            Submitted
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {smsReport.recent.length === 0 && (
                                        <TableRow>
                                            <TableCell
                                                colSpan={5}
                                                className="py-6 text-center text-muted-foreground"
                                            >
                                                <span className="inline-flex items-center gap-2">
                                                    <MessageSquareText className="size-4" />
                                                    No messages have been
                                                    submitted for this campaign
                                                    yet.
                                                </span>
                                            </TableCell>
                                        </TableRow>
                                    )}
                                    {smsReport.recent.map((message) => (
                                        <TableRow key={message.id}>
                                            <TableCell>
                                                <p className="font-medium">
                                                    {message.contact ??
                                                        message.to}
                                                </p>
                                                <p className="font-mono text-xs text-muted-foreground">
                                                    {message.to}
                                                </p>
                                            </TableCell>
                                            <TableCell>
                                                <StatusBadge
                                                    status={message.status}
                                                    label={message.status_label}
                                                />
                                            </TableCell>
                                            <TableCell className="font-mono text-xs">
                                                {message.provider_status ?? '—'}
                                            </TableCell>
                                            <TableCell>
                                                {message.segments}
                                            </TableCell>
                                            <TableCell className="pr-4">
                                                {moment(message.submitted_at)}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                )}

                {emailReport !== null && (
                    <Card className="py-4">
                        <CardHeader>
                            <CardTitle>Email delivery</CardTitle>
                            <CardDescription>
                                Delivery rate, bounces and complaints come from
                                the feedback the mail server posts back.{' '}
                                <Link
                                    href={`${urls.reports.email}?campaign=${campaign.id}`}
                                    className="text-brand underline-offset-4 hover:underline"
                                >
                                    Open the delivery report
                                </Link>
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                <div className="rounded-lg border p-3">
                                    <p className="text-xs text-muted-foreground">
                                        Submitted
                                    </p>
                                    <p className="text-xl font-semibold tabular-nums">
                                        {emailReport.totals.messages.toLocaleString()}
                                    </p>
                                </div>
                                <div className="rounded-lg border p-3">
                                    <p className="text-xs text-muted-foreground">
                                        Delivery rate
                                    </p>
                                    <p className="text-xl font-semibold tabular-nums">
                                        {emailReport.totals.delivery_rate ===
                                        null
                                            ? '—'
                                            : `${emailReport.totals.delivery_rate}%`}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {emailReport.totals.delivered.toLocaleString()}{' '}
                                        delivered
                                    </p>
                                </div>
                                <div className="rounded-lg border p-3">
                                    <p className="text-xs text-muted-foreground">
                                        Awaiting feedback
                                    </p>
                                    <p className="text-xl font-semibold tabular-nums">
                                        {emailReport.totals.pending.toLocaleString()}
                                    </p>
                                </div>
                                <div className="rounded-lg border p-3">
                                    <p className="text-xs text-muted-foreground">
                                        Bounces & complaints
                                    </p>
                                    <p className="text-xl font-semibold tabular-nums">
                                        {(
                                            emailReport.totals.bounced +
                                            emailReport.totals.complained
                                        ).toLocaleString()}
                                    </p>
                                </div>
                            </div>

                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Recipient</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Provider</TableHead>
                                        <TableHead className="pr-4">
                                            Submitted
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {emailReport.recent.length === 0 && (
                                        <TableRow>
                                            <TableCell
                                                colSpan={4}
                                                className="py-6 text-center text-muted-foreground"
                                            >
                                                <span className="inline-flex items-center gap-2">
                                                    <Mail className="size-4" />
                                                    No emails have been
                                                    submitted for this campaign
                                                    yet.
                                                </span>
                                            </TableCell>
                                        </TableRow>
                                    )}
                                    {emailReport.recent.map((message) => (
                                        <TableRow key={message.id}>
                                            <TableCell>
                                                <p className="font-medium">
                                                    {message.contact ??
                                                        message.to}
                                                </p>
                                                <p className="font-mono text-xs text-muted-foreground">
                                                    {message.to}
                                                </p>
                                            </TableCell>
                                            <TableCell>
                                                <StatusBadge
                                                    status={message.status}
                                                    label={message.status_label}
                                                />
                                            </TableCell>
                                            <TableCell className="font-mono text-xs">
                                                {message.provider_status ?? '—'}
                                            </TableCell>
                                            <TableCell className="pr-4">
                                                {moment(message.submitted_at)}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                )}

                <Card className="py-4">
                    <CardHeader>
                        <CardTitle>Version history</CardTitle>
                        <CardDescription>
                            Submitting and approving freezes a delivery
                            snapshot. A campaign that changes after approval
                            must be submitted again before it can launch.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="px-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="pl-4">
                                        Version
                                    </TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Recipients</TableHead>
                                    <TableHead>Estimate</TableHead>
                                    <TableHead>Snapshot</TableHead>
                                    <TableHead>Frozen</TableHead>
                                    <TableHead className="pr-4">By</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {versions.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={7}
                                            className="py-6 text-center text-muted-foreground"
                                        >
                                            Versions appear when the campaign is
                                            submitted for approval.
                                        </TableCell>
                                    </TableRow>
                                )}
                                {versions.map((version) => (
                                    <TableRow key={version.id}>
                                        <TableCell className="pl-4 font-medium">
                                            v{version.version}
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge
                                                status={version.status}
                                                label={version.status_label}
                                            />
                                        </TableCell>
                                        <TableCell>
                                            {version.audience_size.toLocaleString()}
                                        </TableCell>
                                        <TableCell>
                                            {money(version.estimated_cost)}
                                        </TableCell>
                                        <TableCell className="font-mono text-xs text-muted-foreground">
                                            {version.fingerprint.slice(0, 12)}
                                        </TableCell>
                                        <TableCell>
                                            {moment(version.frozen_at)}
                                        </TableCell>
                                        <TableCell className="pr-4">
                                            {version.author ?? '—'}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                <Card className="py-4">
                    <CardHeader>
                        <CardTitle>Recipients</CardTitle>
                        <CardDescription>
                            {recipients.total} recipient
                            {recipients.total === 1 ? '' : 's'} on this
                            campaign.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="px-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="pl-4">
                                        Contact
                                    </TableHead>
                                    <TableHead>
                                        {campaign.channel === 'email'
                                            ? 'Email'
                                            : 'Phone'}
                                    </TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Attempts</TableHead>
                                    <TableHead>Sent</TableHead>
                                    <TableHead className="pr-4">
                                        Error
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {recipients.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={6}
                                            className="py-6 text-center text-muted-foreground"
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
                                            <span className="block text-xs text-muted-foreground">
                                                {campaign.channel === 'email'
                                                    ? (recipient.email ?? '—')
                                                    : (recipient.phone ?? '—')}
                                            </span>
                                        </TableCell>
                                        <TableCell>
                                            {campaign.channel === 'email'
                                                ? (recipient.email ?? '—')
                                                : (recipient.phone ?? '—')}
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
                        <DialogTitle>
                            Reject{' '}
                            {rejectKind === 'audio'
                                ? 'voice audio'
                                : 'campaign'}
                        </DialogTitle>
                        <DialogDescription>
                            {rejectKind === 'audio'
                                ? 'Voice delivery stays blocked until the tenant edits the audio and submits it again.'
                                : 'The submitter sees this reason and any held funds are released.'}
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
                            Reject{' '}
                            {rejectKind === 'audio' ? 'audio' : 'campaign'}
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
