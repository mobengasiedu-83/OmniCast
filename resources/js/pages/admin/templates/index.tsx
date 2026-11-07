import { Head, Link, router } from '@inertiajs/react';
import {
    Archive,
    BarChart3,
    ClipboardCheck,
    LibraryBig,
    Pencil,
    Plus,
    Search,
    Trash2,
    X,
} from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { useConfirm } from '@/components/confirm-dialog';
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
        status: string;
        category: string;
    };
    categories: TemplateCategoryOption[];
    channels: SelectOption[];
    statuses: SelectOption[];
    stats: {
        total: number;
        active: number;
        archived: number;
        awaiting_review: number;
    };
    can: { create: boolean; approve: boolean };
};

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleDateString() : '—';
}

export default function AdminTemplatesIndex({
    templates,
    filters,
    categories,
    channels,
    statuses,
    stats,
    can,
}: PageProps) {
    const [search, setSearch] = useState(filters.search);
    const confirm = useConfirm();

    const hasFilters = Boolean(
        filters.search || filters.channel || filters.status || filters.category,
    );

    const apply = (overrides: Record<string, string | null>): void => {
        router.get(
            urls.admin.templates.index,
            {
                search: filters.search || null,
                channel: filters.channel || null,
                status: filters.status || null,
                category: filters.category || null,
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
            status: null,
            category: null,
        });
    };

    const archive = async (template: TemplateRow): Promise<void> => {
        const confirmed = await confirm({
            title: `Archive "${template.name}"?`,
            description:
                'Archived templates disappear from the library but keep their history. Clients that already copied it keep their version.',
            confirmLabel: 'Archive template',
            cancelLabel: 'Keep it live',
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        router.post(
            urls.admin.templates.archive(template.id),
            {},
            { preserveScroll: true },
        );
    };

    const destroy = async (template: TemplateRow): Promise<void> => {
        const confirmed = await confirm({
            title: `Delete "${template.name}"?`,
            description:
                'The catalogue entry is removed for good. Campaigns that already used its copy are not affected.',
            confirmLabel: 'Delete template',
            cancelLabel: 'Keep template',
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        router.delete(urls.admin.templates.destroy(template.id), {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Template catalogue" />

            <div className="flex flex-col gap-4">
                <PageHeader
                    icon={LibraryBig}
                    title="Template catalogue"
                    description="The starting points every client browses. Catalogue copy is platform property, so a client that edits it keeps its own copy."
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            {can.approve && (
                                <Button variant="outline" asChild>
                                    <Link
                                        href={
                                            urls.admin.templateApprovals.index
                                        }
                                    >
                                        <ClipboardCheck />
                                        Review queue
                                    </Link>
                                </Button>
                            )}
                            <Button variant="outline" asChild>
                                <Link href={urls.admin.templateUsage.index}>
                                    <BarChart3 />
                                    Usage
                                </Link>
                            </Button>
                            {can.create && (
                                <Button asChild>
                                    <Link href={urls.admin.templates.create}>
                                        <Plus />
                                        New template
                                    </Link>
                                </Button>
                            )}
                        </div>
                    }
                />

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        title="Catalogue templates"
                        value={stats.total}
                        icon={LibraryBig}
                    />
                    <StatCard
                        title="Active"
                        value={stats.active}
                        tone="success"
                    />
                    <StatCard
                        title="Archived"
                        value={stats.archived}
                        tone="neutral"
                    />
                    <StatCard
                        title="Tenant reviews waiting"
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
                                    placeholder="Search name or copy"
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
                            value={filters.category || 'all'}
                            onValueChange={(value) =>
                                apply({
                                    category: value === 'all' ? null : value,
                                })
                            }
                        >
                            <SelectTrigger className="w-52">
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
                                        {category.name}
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
                        <CardTitle>Catalogue</CardTitle>
                        <CardDescription>
                            {templates.total} templates published by the
                            platform.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="px-0">
                        {templates.data.length === 0 ? (
                            <EmptyState
                                icon={LibraryBig}
                                title="No catalogue templates yet"
                                description="Add the first starting point so clients have something to copy."
                                action={
                                    can.create && (
                                        <Button asChild>
                                            <Link
                                                href={
                                                    urls.admin.templates.create
                                                }
                                            >
                                                <Plus />
                                                New template
                                            </Link>
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
                                        <TableHead>Channel</TableHead>
                                        <TableHead>Category</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Uses</TableHead>
                                        <TableHead>Updated</TableHead>
                                        <TableHead className="pr-6 text-right">
                                            Actions
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
                                                <p className="line-clamp-1 max-w-md text-xs text-muted-foreground">
                                                    {template.description ??
                                                        template.body}
                                                </p>
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant="outline">
                                                    {template.channel_label}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-sm text-muted-foreground">
                                                {template.category?.name ?? '—'}
                                            </TableCell>
                                            <TableCell>
                                                <StatusBadge
                                                    status={template.status}
                                                    label={
                                                        template.status_label
                                                    }
                                                />
                                            </TableCell>
                                            <TableCell className="text-sm tabular-nums">
                                                {template.usage_count}
                                            </TableCell>
                                            <TableCell className="text-sm text-muted-foreground">
                                                {formatDate(
                                                    template.updated_at,
                                                )}
                                            </TableCell>
                                            <TableCell className="pr-6">
                                                <div className="flex items-center justify-end gap-1">
                                                    <Button
                                                        asChild
                                                        size="icon"
                                                        variant="ghost"
                                                        aria-label="Edit template"
                                                    >
                                                        <Link
                                                            href={urls.admin.templates.edit(
                                                                template.id,
                                                            )}
                                                        >
                                                            <Pencil />
                                                        </Link>
                                                    </Button>
                                                    {template.status !==
                                                        'archived' && (
                                                        <Button
                                                            type="button"
                                                            size="icon"
                                                            variant="ghost"
                                                            aria-label="Archive template"
                                                            onClick={() =>
                                                                void archive(
                                                                    template,
                                                                )
                                                            }
                                                        >
                                                            <Archive />
                                                        </Button>
                                                    )}
                                                    <Button
                                                        type="button"
                                                        size="icon"
                                                        variant="ghost"
                                                        className="text-destructive hover:text-destructive"
                                                        aria-label="Delete template"
                                                        onClick={() =>
                                                            void destroy(
                                                                template,
                                                            )
                                                        }
                                                    >
                                                        <Trash2 />
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

                <Pagination paginator={templates} noun="templates" />
            </div>
        </>
    );
}

AdminTemplatesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Catalogue',
            href: urls.admin.templates.index,
        },
    ],
};
