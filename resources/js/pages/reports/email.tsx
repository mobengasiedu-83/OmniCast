import { Head, router } from '@inertiajs/react';
import { CheckCheck, Mail, ShieldAlert, Wallet } from 'lucide-react';
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
import type { EmailMessageReport } from '@/types';

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
    report: EmailMessageReport;
    campaigns: CampaignOption[];
};

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

export default function EmailReport({ filters, report, campaigns }: PageProps) {
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);

    const apply = (overrides: Record<string, string | number | null>): void => {
        router.get(
            urls.reports.email,
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
            <Head title="Email messages" />

            <div className="flex flex-col gap-4">
                <PageHeader
                    icon={Mail}
                    title="Email messages"
                    description="Delivery rate, bounces and complaints, read from the feedback the mail server posts back."
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
                                        <SelectValue placeholder="Every email campaign" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            Every email campaign
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

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        title="Emails submitted"
                        value={totals.messages.toLocaleString()}
                        description={`${totals.pending.toLocaleString()} awaiting feedback`}
                        icon={Mail}
                        tone="brand"
                    />
                    <StatCard
                        title="Delivery rate"
                        value={
                            totals.delivery_rate === null
                                ? '—'
                                : `${totals.delivery_rate}%`
                        }
                        description={`${totals.delivered.toLocaleString()} delivered`}
                        icon={CheckCheck}
                        tone="success"
                    />
                    <StatCard
                        title="Bounces & complaints"
                        value={(
                            totals.bounced + totals.complained
                        ).toLocaleString()}
                        description={`${totals.bounced.toLocaleString()} bounced · ${totals.complained.toLocaleString()} complaints`}
                        icon={ShieldAlert}
                        tone="warning"
                    />
                    <StatCard
                        title="Email spend"
                        value={money(totals.spend)}
                        description="Billed per recipient submitted"
                        icon={Wallet}
                        tone="neutral"
                    />
                </div>

                <Card className="py-4">
                    <CardHeader>
                        <CardTitle>Recent messages</CardTitle>
                        <CardDescription>
                            The last {report.recent.length} message
                            {report.recent.length === 1 ? '' : 's'} in this
                            window, with the mailbox feedback behind each one.
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
                                    <TableHead>Provider</TableHead>
                                    <TableHead>Attempt</TableHead>
                                    <TableHead>Submitted</TableHead>
                                    <TableHead>Delivered</TableHead>
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
                                                icon={Mail}
                                                title="No messages in this window"
                                                description="Messages appear here as soon as an email campaign is dispatched."
                                            />
                                        </TableCell>
                                    </TableRow>
                                )}
                                {report.recent.map((message) => (
                                    <TableRow key={message.id}>
                                        <TableCell className="pl-4">
                                            <p className="font-medium">
                                                {message.contact ?? message.to}
                                            </p>
                                            <p className="text-muted-foreground text-xs">
                                                {message.subject}
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
                                        <TableCell>{message.attempts}</TableCell>
                                        <TableCell>
                                            {moment(message.submitted_at)}
                                        </TableCell>
                                        <TableCell>
                                            {moment(message.delivered_at)}
                                        </TableCell>
                                        <TableCell className="pr-4">
                                            {message.error_message ?? '—'}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

EmailReport.layout = {
    breadcrumbs: [
        {
            title: 'Email messages',
            href: urls.reports.email,
        },
    ],
};
