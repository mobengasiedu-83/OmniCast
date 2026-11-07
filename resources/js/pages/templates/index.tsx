import { Head, Link, router } from '@inertiajs/react';
import {
    Filter,
    LayoutTemplate,
    Pencil,
    Plus,
    Search,
    Sparkles,
    Star,
    Wand2,
    X,
} from 'lucide-react';
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
import { cn } from '@/lib/utils';
import { urls } from '@/lib/urls';
import type {
    Paginated,
    SelectOption,
    TemplateCategoryOption,
    TemplateRow,
} from '@/types';

type PageProps = {
    templates: Paginated<TemplateRow>;
    filters: {
        search: string;
        channel: string;
        category: string;
        favourites: boolean;
        mine: boolean;
    };
    categories: TemplateCategoryOption[];
    channels: SelectOption[];
    counts: Record<string, number>;
    stats: {
        total: number;
        mine: number;
        favourites: number;
        awaiting_review: number;
    };
    can: { create: boolean; manage_categories: boolean };
};

export default function TemplatesIndex({
    templates,
    filters,
    categories,
    channels,
    counts,
    stats,
    can,
}: PageProps) {
    const [search, setSearch] = useState(filters.search);

    const hasFilters = Boolean(
        filters.search ||
        filters.channel ||
        filters.category ||
        filters.favourites ||
        filters.mine,
    );

    const apply = (overrides: Record<string, string | null>): void => {
        router.get(
            urls.templates.index,
            {
                search: filters.search || null,
                channel: filters.channel || null,
                category: filters.category || null,
                favourites: filters.favourites ? '1' : null,
                mine: filters.mine ? '1' : null,
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
        apply({
            search: null,
            channel: null,
            category: null,
            favourites: null,
            mine: null,
        });
    };

    const favourite = (template: TemplateRow): void => {
        router.post(
            urls.templates.favourite(template.id),
            {},
            { preserveScroll: true },
        );
    };

    const customise = (template: TemplateRow): void => {
        router.post(
            urls.templates.customise(template.id),
            {},
            { preserveScroll: true },
        );
    };

    const tabs = [
        { value: '', label: 'All channels', count: counts.all ?? 0 },
        ...channels.map((channel) => ({
            value: channel.value,
            label: channel.label,
            count: counts[channel.value] ?? 0,
        })),
    ];

    return (
        <>
            <Head title="Templates" />

            <div className="flex flex-col gap-4">
                <PageHeader
                    icon={LayoutTemplate}
                    title="Templates"
                    description="Reusable message copy for every channel. Start from the platform catalogue, keep your own versions, and drop any template straight into a campaign."
                    actions={
                        can.create && (
                            <Button asChild>
                                <Link href={urls.templates.create}>
                                    <Plus />
                                    New template
                                </Link>
                            </Button>
                        )
                    }
                />

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        title="Available templates"
                        value={stats.total}
                        icon={LayoutTemplate}
                    />
                    <StatCard
                        title="Your own versions"
                        value={stats.mine}
                        icon={Wand2}
                        tone="brand"
                    />
                    <StatCard
                        title="Favourites"
                        value={stats.favourites}
                        icon={Star}
                        tone="signal"
                    />
                    <StatCard
                        title="Awaiting review"
                        value={stats.awaiting_review}
                        tone="warning"
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
                                    placeholder="Search template or copy"
                                    className="pl-8"
                                />
                            </div>
                            <Button type="submit" variant="secondary">
                                Search
                            </Button>
                        </form>

                        <Select
                            value={filters.category || 'all'}
                            onValueChange={(value) =>
                                apply({
                                    category: value === 'all' ? null : value,
                                })
                            }
                        >
                            <SelectTrigger className="w-56">
                                <SelectValue placeholder="All categories" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    All categories
                                </SelectItem>
                                {categories.map((category) => (
                                    <SelectItem
                                        key={category.slug}
                                        value={category.slug}
                                    >
                                        {category.name} (
                                        {category.templates_count})
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <Button
                            type="button"
                            variant={filters.favourites ? 'default' : 'outline'}
                            size="sm"
                            onClick={() =>
                                apply({
                                    favourites: filters.favourites ? null : '1',
                                })
                            }
                        >
                            <Star
                                className={cn(
                                    'size-4',
                                    filters.favourites && 'fill-current',
                                )}
                            />
                            Favourites
                        </Button>

                        <Button
                            type="button"
                            variant={filters.mine ? 'default' : 'outline'}
                            size="sm"
                            onClick={() =>
                                apply({ mine: filters.mine ? null : '1' })
                            }
                        >
                            <Filter className="size-4" />
                            My versions
                        </Button>

                        {hasFilters && (
                            <Button variant="ghost" size="sm" onClick={clear}>
                                <X className="size-4" />
                                Clear
                            </Button>
                        )}
                    </CardContent>
                </Card>

                <div className="flex flex-wrap items-center gap-1.5">
                    {tabs.map((tab) => (
                        <Button
                            key={tab.value || 'all'}
                            type="button"
                            size="sm"
                            variant={
                                filters.channel === tab.value
                                    ? 'default'
                                    : 'outline'
                            }
                            onClick={() =>
                                apply({ channel: tab.value || null })
                            }
                        >
                            {tab.label}
                            <span className="ml-1 text-xs text-muted-foreground tabular-nums">
                                {tab.count}
                            </span>
                        </Button>
                    ))}
                </div>

                {templates.data.length === 0 ? (
                    <Card>
                        <EmptyState
                            icon={LayoutTemplate}
                            title="No templates match those filters"
                            description="Adjust the search or clear the filters to browse the whole library again."
                            action={
                                hasFilters && (
                                    <Button variant="outline" onClick={clear}>
                                        Clear filters
                                    </Button>
                                )
                            }
                        />
                    </Card>
                ) : (
                    <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        {templates.data.map((template) => (
                            <Card key={template.id} className="flex flex-col">
                                <CardHeader className="gap-2">
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="min-w-0">
                                            <CardTitle className="truncate text-sm">
                                                {template.name}
                                            </CardTitle>
                                            <CardDescription className="mt-1 text-xs">
                                                {template.category?.name ??
                                                    'Uncategorised'}
                                                {template.is_system
                                                    ? ' · Platform catalogue'
                                                    : ` · ${template.client ?? 'Your library'}`}
                                            </CardDescription>
                                        </div>
                                        <StatusBadge
                                            status={template.status}
                                            label={template.status_label}
                                        />
                                    </div>
                                    <div className="flex flex-wrap items-center gap-1.5">
                                        <Badge variant="outline">
                                            {template.channel_label}
                                        </Badge>
                                        {template.is_system && (
                                            <Badge variant="info">
                                                Catalogue
                                            </Badge>
                                        )}
                                        {template.is_favourite && (
                                            <Badge variant="warning">
                                                <Star className="size-3 fill-current" />
                                                Favourite
                                            </Badge>
                                        )}
                                    </div>
                                </CardHeader>
                                <CardContent className="flex flex-1 flex-col gap-3">
                                    {template.subject && (
                                        <p className="text-xs font-semibold">
                                            {template.subject}
                                        </p>
                                    )}
                                    <p className="line-clamp-3 text-sm text-muted-foreground">
                                        {template.body}
                                    </p>

                                    {template.variables.length > 0 && (
                                        <div className="flex flex-wrap gap-1">
                                            {template.variables
                                                .slice(0, 6)
                                                .map((variable) => (
                                                    <span
                                                        key={variable}
                                                        className="rounded bg-muted px-1.5 py-0.5 font-mono text-[10px] text-muted-foreground"
                                                    >
                                                        {variable}
                                                    </span>
                                                ))}
                                        </div>
                                    )}

                                    {template.rejection_reason && (
                                        <p className="rounded-md bg-destructive/10 p-2 text-xs text-destructive">
                                            Returned:{' '}
                                            {template.rejection_reason}
                                        </p>
                                    )}

                                    <p className="text-xs text-muted-foreground">
                                        Used {template.usage_count}{' '}
                                        {template.usage_count === 1
                                            ? 'time'
                                            : 'times'}{' '}
                                        in {template.campaigns_count}{' '}
                                        {template.campaigns_count === 1
                                            ? 'campaign'
                                            : 'campaigns'}
                                        {template.author
                                            ? ` · ${template.author}`
                                            : ''}
                                    </p>
                                    <div className="mt-auto flex flex-wrap items-center gap-1.5 pt-1">
                                        {template.can.use && (
                                            <Button asChild size="sm">
                                                <Link
                                                    href={`${urls.campaigns.create}?template=${template.id}`}
                                                >
                                                    <Sparkles />
                                                    Use in campaign
                                                </Link>
                                            </Button>
                                        )}
                                        {template.can.update ? (
                                            <Button
                                                asChild
                                                size="sm"
                                                variant="outline"
                                            >
                                                <Link
                                                    href={urls.templates.edit(
                                                        template.id,
                                                    )}
                                                >
                                                    <Pencil />
                                                    Edit
                                                </Link>
                                            </Button>
                                        ) : template.is_system ? (
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    customise(template)
                                                }
                                            >
                                                <Wand2 />
                                                Customise
                                            </Button>
                                        ) : null}
                                        <Button
                                            type="button"
                                            size="icon"
                                            variant="ghost"
                                            aria-label={
                                                template.is_favourite
                                                    ? 'Remove from favourites'
                                                    : 'Add to favourites'
                                            }
                                            onClick={() => favourite(template)}
                                        >
                                            <Star
                                                className={cn(
                                                    'size-4',
                                                    template.is_favourite &&
                                                        'fill-current text-warning',
                                                )}
                                            />
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}

                <Pagination paginator={templates} noun="templates" />
            </div>
        </>
    );
}

TemplatesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Templates',
            href: urls.templates.index,
        },
    ],
};
