import { Head, Link, router } from '@inertiajs/react';
import { BarChart3, LibraryBig, Search, TrendingUp, X } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import EmptyState from '@/components/empty-state';
import PageHeader from '@/components/page-header';
import Pagination from '@/components/pagination';
import StatCard from '@/components/stat-card';
import StatusBadge from '@/components/status-badge';
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
import type { Paginated, SelectOption, TemplateUsageRow } from '@/types';

type PageProps = {
    templates: Paginated<TemplateUsageRow>;
    filters: { search: string; channel: string; ownership: string };
    channels: SelectOption[];
    ownerships: SelectOption[];
    stats: { uses: number; templates_used: number; campaigns: number };
};

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleDateString() : 'Never used';
}

export default function AdminTemplateUsage({
    templates,
    filters,
    channels,
    ownerships,
    stats,
}: PageProps) {
    const [search, setSearch] = useState(filters.search);

    const hasFilters = Boolean(
        filters.search || filters.channel || filters.ownership,
    );

    const apply = (overrides: Record<string, string | null>): void => {
        router.get(
            urls.admin.templateUsage.index,
            {
                search: filters.search || null,
                channel: filters.channel || null,
                ownership: filters.ownership || null,
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
        apply({ search: null, channel: null, ownership: null });
    };

    return (
        <>
            <Head title="Template usage" />

            <div className="flex flex-col gap-4">
                <PageHeader
                    icon={BarChart3}
                    title="Template usage"
                    description="Which starting points clients actually reach for. Every campaign saved from a template counts as one use."
                    actions={
                        <Button variant="outline" asChild>
                            <Link href={urls.admin.templates.index}>
                                <LibraryBig />
                                Catalogue
                            </Link>
                        </Button>
                    }
                />

                <div className="grid gap-3 sm:grid-cols-3">
                    <StatCard
                        title="Template uses"
                        value={stats.uses}
                        icon={TrendingUp}
                        tone="brand"
                    />
                    <StatCard
                        title="Templates used at least once"
                        value={stats.templates_used}
                        tone="success"
                    />
                    <StatCard
                        title="Campaigns started from a template"
                        value={stats.campaigns}
                        tone="signal"
                    />
                </div>

                <Card className="py-3">
                    <CardContent className="flex flex-wrap items-center gap-2 px-4">
                        <form
                            onSubmit={submit}
                            className="flex flex-1 items-center gap-2 sm:flex-none"
                        >
                            <div className="relative w-full sm:w-64">
                                <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    placeholder="Search template or client"
                                    className="pl-8"
                                />
                            </div>
                            <Button type="submit" variant="secondary">
                                Search
                            </Button>
                        </form>

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

                        <Select
                            value={filters.ownership || 'all'}
                            onValueChange={(value) =>
                                apply({
                                    ownership: value === 'all' ? null : value,
                                })
                            }
                        >
                            <SelectTrigger className="w-48">
                                <SelectValue placeholder="All owners" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All owners</SelectItem>
                                {ownerships.map((ownership) => (
                                    <SelectItem
                                        key={ownership.value}
                                        value={ownership.value}
                                    >
                                        {ownership.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        {hasFilters && (
                            <Button variant="ghost" size="sm" onClick={clear}>
                                <X className="size-4" />
                                Clear
                            </Button>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Usage by template</CardTitle>
                        <CardDescription>
                            Ordered by how many campaigns each template has
                            started.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="px-0">
                        {templates.data.length === 0 ? (
                            <EmptyState
                                icon={BarChart3}
                                title="No usage recorded yet"
                                description="Once a campaign is saved from a template it shows up here."
                                action={
                                    hasFilters && (
                                        <Button
                                            variant="outline"
                                            onClick={clear}
                                        >
                                            Clear filters
                                        </Button>
                                    )
                                }
                            />
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="pl-6">
                                            Template
                                        </TableHead>
                                        <TableHead>Owner</TableHead>
                                        <TableHead>Channel</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Uses</TableHead>
                                        <TableHead>Campaigns</TableHead>
                                        <TableHead className="pr-6">
                                            Last used
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {templates.data.map((template) => (
                                        <TableRow key={template.id}>
                                            <TableCell className="pl-6">
                                                <p className="font-medium">
                                                    {template.name}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {template.category?.name ??
                                                        'Uncategorised'}
                                                </p>
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex flex-col gap-1">
                                                    <Badge
                                                        variant={
                                                            template.is_system
                                                                ? 'info'
                                                                : 'outline'
                                                        }
                                                    >
                                                        {template.ownership}
                                                    </Badge>
                                                    <span className="text-xs text-muted-foreground">
                                                        {template.client ??
                                                            'Platform'}
                                                    </span>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant="outline">
                                                    {template.channel_label}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                <StatusBadge
                                                    status={template.status}
                                                    label={
                                                        template.status_label
                                                    }
                                                />
                                            </TableCell>
                                            <TableCell className="text-sm font-medium tabular-nums">
                                                {template.usage_count}
                                            </TableCell>
                                            <TableCell className="text-sm tabular-nums">
                                                {template.campaigns_count}
                                            </TableCell>
                                            <TableCell className="pr-6 text-sm text-muted-foreground">
                                                {formatDate(
                                                    template.last_used_at,
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>

                <Pagination paginator={templates} noun="templates" />
            </div>
        </>
    );
}

AdminTemplateUsage.layout = {
    breadcrumbs: [
        {
            title: 'Catalogue',
            href: urls.admin.templates.index,
        },
        {
            title: 'Template usage',
            href: urls.admin.templateUsage.index,
        },
    ],
};
