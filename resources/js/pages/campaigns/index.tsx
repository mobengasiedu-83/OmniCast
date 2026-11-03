import { Head, Link, router } from '@inertiajs/react';
import { Megaphone, Plus, Search, X } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import EmptyState from '@/components/empty-state';
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
import { Input } from '@/components/ui/input';
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
import { cn } from '@/lib/utils';
import type { CampaignRow, Paginated, SelectOption } from '@/types';

type PageProps = {
    campaigns: Paginated<CampaignRow>;
    filters: {
        status: string;
        channel: string;
        search: string;
    };
    statuses: SelectOption[];
    channels: SelectOption[];
    can: {
        create: boolean;
    };
};

function money(value: number): string {
    return `GHS ${value.toLocaleString('en-GH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

export default function CampaignsIndex({
    campaigns,
    filters,
    statuses,
    channels,
    can,
}: PageProps) {
    const [search, setSearch] = useState(filters.search);

    const hasFilters = Boolean(
        filters.search || filters.status || filters.channel,
    );

    const apply = (overrides: Record<string, string | null>): void => {
        router.get(
            urls.campaigns.index,
            {
                search: filters.search || null,
                status: filters.status || null,
                channel: filters.channel || null,
                ...overrides,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        apply({ search: search || null });
    };

    const clear = (): void => {
        setSearch('');
        apply({ search: null, status: null, channel: null });
    };

    return (
        <>
            <Head title="Campaigns" />

            <div className="flex flex-col gap-4">
                <PageHeader
                    icon={Megaphone}
                    title="Campaigns"
                    description="Draft, review and launch voice and SMS campaigns."
                    actions={
                        can.create && (
                            <Button asChild>
                                <Link href={urls.campaigns.create}>
                                    <Plus />
                                    New campaign
                                </Link>
                            </Button>
                        )
                    }
                />

                <Card className="py-3">
                    <CardContent className="flex flex-wrap items-center gap-2 px-4">
                        <form
                            onSubmit={submit}
                            className="flex flex-1 items-center gap-2 sm:flex-none"
                        >
                            <div className="relative w-full sm:w-72">
                                <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2" />
                                <Input
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    placeholder="Search name or reference"
                                    className="pl-8"
                                />
                            </div>

                            <Button
                                type="submit"
                                variant="secondary"
                                size="icon"
                                aria-label="Search campaigns"
                            >
                                <Search />
                            </Button>
                        </form>

                        <Select
                            value={filters.status || 'all'}
                            onValueChange={(value) =>
                                apply({
                                    status: value === 'all' ? null : value,
                                })
                            }
                        >
                            <SelectTrigger className="w-44">
                                <SelectValue placeholder="All statuses" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    All statuses
                                </SelectItem>
                                {statuses.map((status) => (
                                    <SelectItem
                                        key={status.value}
                                        value={status.value}
                                    >
                                        {status.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <Select
                            value={filters.channel || 'all'}
                            onValueChange={(value) =>
                                apply({
                                    channel: value === 'all' ? null : value,
                                })
                            }
                        >
                            <SelectTrigger className="w-40">
                                <SelectValue placeholder="All channels" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    All channels
                                </SelectItem>
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

                        {hasFilters && (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={clear}
                                className="text-muted-foreground"
                            >
                                <X />
                                Clear
                            </Button>
                        )}
                    </CardContent>
                </Card>

                <Card className="gap-0 overflow-hidden py-0">
                    <CardHeader className="flex flex-row items-center justify-between gap-2 border-b border-border/60 py-3">
                        <CardTitle className="text-sm font-medium">
                            {campaigns.total}{' '}
                            {campaigns.total === 1 ? 'campaign' : 'campaigns'}
                        </CardTitle>
                        <CardDescription className="text-xs">
                            Page {campaigns.current_page} of{' '}
                            {campaigns.last_page}
                        </CardDescription>
                    </CardHeader>

                    <CardContent className="px-0">{campaigns.data.length === 0 ? (
                            <EmptyState
                                icon={Megaphone}
                                title="No campaigns match these filters"
                                description="Try a different search term, or clear the filters to see everything."
                                action={
                                    <Button variant="outline" size="sm" onClick={clear}>
                                        Clear filters
                                    </Button>
                                }
                            />
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Campaign</TableHead>
                                        <TableHead>Channel</TableHead>
                                        <TableHead>Audience</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">
                                            Recipients
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Spend
                                        </TableHead>
                                        <TableHead>Scheduled</TableHead>
                                        <TableHead />
                                    </TableRow>
                                </TableHeader>

                                <TableBody>{campaigns.data.map((campaign) => (
                                        <TableRow key={campaign.id}>
                                            <TableCell>
                                                <Link
                                                    href={urls.campaigns.show(
                                                        campaign.id,
                                                    )}
                                                    className="hover:text-brand font-medium transition-colors"
                                                >
                                                    {campaign.name}
                                                </Link>
                                                <p className="text-muted-foreground text-xs">
                                                    {campaign.reference}
                                                    {campaign.client
                                                        ? ` · ${campaign.client}`
                                                        : ''}
                                                </p>
                                            </TableCell>

                                            <TableCell>
                                                <span className="flex items-center gap-2 text-sm">
                                                    <span
                                                        className={cn(
                                                            'size-2 rounded-full',
                                                            campaign.channel ===
                                                                'voice'
                                                                ? 'brand-gradient'
                                                                : 'bg-signal',
                                                        )}
                                                    />
                                                    {campaign.channel_label}
                                                </span>
                                                {campaign.sender && (
                                                    <p className="text-muted-foreground mt-0.5 text-xs">
                                                        {campaign.sender}
                                                    </p>
                                                )}
                                            </TableCell>

                                            <TableCell className="text-sm">
                                                {campaign.audience}
                                            </TableCell>

                                            <TableCell>
                                                <StatusBadge
                                                    status={campaign.status}
                                                    label={
                                                        campaign.status_label
                                                    }
                                                />
                                            </TableCell>

                                            <TableCell className="text-right tabular-nums">
                                                {campaign.estimated_recipients ??
                                                    '—'}
                                            </TableCell>

                                            <TableCell className="text-right tabular-nums">
                                                {money(
                                                    campaign.actual_cost > 0
                                                        ? campaign.actual_cost
                                                        : campaign.estimated_cost,
                                                )}
                                            </TableCell>

                                            <TableCell className="text-muted-foreground text-xs">
                                                {campaign.scheduled_at
                                                    ? new Date(
                                                          campaign.scheduled_at,
                                                      ).toLocaleString()
                                                    : 'Immediate'}
                                            </TableCell>

                                            <TableCell className="text-right">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <Link
                                                        href={urls.campaigns.show(
                                                            campaign.id,
                                                        )}
                                                    >
                                                        View
                                                    </Link>
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    ))}</TableBody>
                            </Table>
                        )}</CardContent>
                </Card>

                <Pagination paginator={campaigns} noun="campaigns" />
            </div>
        </>
    );
}

CampaignsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Campaigns',
            href: urls.campaigns.index,
        },
    ],
};
