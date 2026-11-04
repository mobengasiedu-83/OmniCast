import { Head, router } from '@inertiajs/react';
import {
    Clock,
    PhoneCall,
    PhoneIncoming,
    PhoneOff,
    ShieldOff,
    Wallet,
} from 'lucide-react';
import { useState, type FormEvent } from 'react';
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
import { Input } from '@/components/ui/input';
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
import { urls } from '@/lib/urls';
import type { VoiceCallReport } from '@/types';

type CampaignOption = {
    id: number;
    name: string;
    reference: string;
};

type PageProps = {
    filters: {
        from: string;
        to: string;
        campaign: number | null;
    };
    report: VoiceCallReport;
    campaigns: CampaignOption[];
};

/**
 * Seconds as `m:ss`, the way a talk time reads on a call log.
 */
function duration(seconds: number): string {
    if (seconds <= 0) {
        return '—';
    }

    const minutes = Math.floor(seconds / 60);

    return `${minutes}:${String(seconds % 60).padStart(2, '0')}`;
}

function moment(value: string | null): string {
    return value
        ? new Date(value).toLocaleString(undefined, {
              dateStyle: 'medium',
              timeStyle: 'short',
          })
        : '—';
}

function money(value: number): string {
    return `GHS ${value.toLocaleString('en-GH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

export default function VoiceReport({ filters, report, campaigns }: PageProps) {
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);

    const apply = (overrides: Record<string, string | number | null>): void => {
        router.get(
            urls.reports.voice,
            {
                from: filters.from,
                to: filters.to,
                campaign: filters.campaign,
                ...overrides,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        apply({ from: from || null, to: to || null });
    };

    const totals = report.totals;

    return (
        <>
            <Head title="Voice calls" />

            <div className="flex flex-col gap-4">
                <PageHeader
                    icon={PhoneCall}
                    title="Voice calls"
                    description="Answer rate, talk time and key presses, read from the call log the switch posts back."
                />

                <Card className="py-4">
                    <CardContent>
                        <form
                            onSubmit={submit}
                            className="flex flex-wrap items-end gap-3"
                        >
                            <div className="grid gap-1.5">
                                <Label htmlFor="from">From</Label>
                                <Input
                                    id="from"
                                    type="date"
                                    value={from}
                                    onChange={(event) =>
                                        setFrom(event.target.value)
                                    }
                                />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="to">To</Label>
                                <Input
                                    id="to"
                                    type="date"
                                    value={to}
                                    onChange={(event) =>
                                        setTo(event.target.value)
                                    }
                                />
                            </div>
                            <div className="grid min-w-56 gap-1.5">
                                <Label htmlFor="campaign">Campaign</Label>
                                <Select
                                    value={
                                        filters.campaign === null
                                            ? 'all'
                                            : String(filters.campaign)
                                    }
                                    onValueChange={(value) =>
                                        apply({
                                            campaign:
                                                value === 'all' ? null : value,
                                        })
                                    }
                                >
                                    <SelectTrigger id="campaign">
                                        <SelectValue placeholder="Every voice campaign" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            Every voice campaign
                                        </SelectItem>
                                        {campaigns.map((campaign) => (
                                            <SelectItem
                                                key={campaign.id}
                                                value={String(campaign.id)}
                                            >
                                                {campaign.name} ·{' '}
                                                {campaign.reference}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <Button type="submit" variant="outline">
                                Apply
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    <StatCard
                        title="Calls dialled"
                        value={totals.calls.toLocaleString()}
                        description={`${totals.answered.toLocaleString()} answered`}
                        icon={PhoneCall}
                        tone="brand"
                    />
                    <StatCard
                        title="Answer rate"
                        value={
                            totals.answer_rate === null
                                ? '—'
                                : `${totals.answer_rate}%`
                        }
                        description={`${totals.no_answer.toLocaleString()} no answer · ${totals.busy.toLocaleString()} busy`}
                        icon={PhoneIncoming}
                        tone="success"
                    />
                    <StatCard
                        title="Average talk time"
                        value={duration(totals.average_duration)}
                        description="Answered calls only"
                        icon={Clock}
                        tone="signal"
                    />
                    <StatCard
                        title="Opt-outs"
                        value={totals.opt_outs.toLocaleString()}
                        description="Pressed the opt-out key"
                        icon={ShieldOff}
                        tone="warning"
                    />
                    <StatCard
                        title="Call spend"
                        value={money(totals.spend)}
                        description={`${totals.failed.toLocaleString()} failed · ${totals.canceled.toLocaleString()} cancelled`}
                        icon={Wallet}
                        tone="neutral"
                    />
                </div>

                <div className="grid gap-4 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
                    <Card className="py-4">
                        <CardHeader>
                            <CardTitle>Recent calls</CardTitle>
                            <CardDescription>
                                The last {report.recent.length} call
                                {report.recent.length === 1 ? '' : 's'} in this
                                window.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="px-0">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="pl-4">
                                            Recipient
                                        </TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Attempt</TableHead>
                                        <TableHead>Talk time</TableHead>
                                        <TableHead>Key press</TableHead>
                                        <TableHead>Dialled</TableHead>
                                        <TableHead className="pr-4">
                                            Outcome
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {report.recent.length === 0 && (
                                        <TableRow>
                                            <TableCell colSpan={7}>
                                                <EmptyState
                                                    icon={PhoneOff}
                                                    title="No calls in this window"
                                                    description="Voice calls appear here as soon as a voice campaign is dispatched."
                                                />
                                            </TableCell>
                                        </TableRow>
                                    )}
                                    {report.recent.map((call) => (
                                        <TableRow key={call.id}>
                                            <TableCell className="pl-4">
                                                <p className="font-medium">
                                                    {call.contact ?? call.to}
                                                </p>
                                                <p className="text-muted-foreground text-xs">
                                                    {call.contact
                                                        ? call.to
                                                        : call.reference}
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
                                                {duration(
                                                    call.duration_seconds,
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                {call.dtmf_digits
                                                    ? `${call.dtmf_digits} · ${
                                                          call.ivr_action_label ??
                                                          'Unmapped'
                                                      }`
                                                    : '—'}
                                            </TableCell>
                                            <TableCell>
                                                {moment(call.dialled_at)}
                                            </TableCell>
                                            <TableCell className="pr-4">
                                                {call.error_message ?? '—'}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>

                    <Card className="py-4">
                        <CardHeader>
                            <CardTitle>Key presses</CardTitle>
                            <CardDescription>
                                What recipients pressed on the IVR menu, so the
                                prompt can be tuned from real behaviour.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {report.digits.length === 0 ? (
                                <EmptyState
                                    icon={PhoneOff}
                                    title="No key presses yet"
                                    description="Digits appear once a recipient presses a key on a call with an IVR menu."
                                />
                            ) : (
                                <ul className="grid gap-2">
                                    {report.digits.map((digit) => (
                                        <li
                                            key={`${digit.digit}-${digit.action}`}
                                            className="flex items-center justify-between gap-3 rounded-lg border p-2"
                                        >
                                            <span className="flex items-center gap-2">
                                                <span className="bg-muted flex size-8 items-center justify-center rounded-md font-mono text-sm font-semibold">
                                                    {digit.digit ?? '?'}
                                                </span>
                                                <span className="text-sm">
                                                    {digit.action_label ??
                                                        'Unmapped key'}
                                                </span>
                                            </span>
                                            <span className="text-muted-foreground text-sm tabular-nums">
                                                {digit.total}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

VoiceReport.layout = {
    breadcrumbs: [
        {
            title: 'Voice calls',
            href: urls.reports.voice,
        },
    ],
};

