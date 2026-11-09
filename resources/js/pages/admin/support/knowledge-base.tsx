import { Head, useForm } from '@inertiajs/react';
import { Barcode, Pencil, Trash2 } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { useConfirm } from '@/components/confirm-dialog';
import PageHeader from '@/components/page-header';
import StatCard from '@/components/stat-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import articles from '@/routes/admin/support/knowledge-base';

type ArticleRow = {
    id: number;
    title: string;
    slug: string;
    category: string | null;
    excerpt: string | null;
    body: string;
    is_published: boolean;
    is_featured: boolean;
    sort_order: number;
    views: number;
    published_at: string | null;
    updated_at: string | null;
};

type PageProps = {
    articles: ArticleRow[];
    stats: { total: number; published: number; drafts: number; views: number };
};

type ArticleForm = {
    title: string;
    category: string;
    excerpt: string;
    body: string;
    is_published: boolean;
    is_featured: boolean;
    sort_order: string;
};

const BLANK: ArticleForm = {
    title: '',
    category: '',
    excerpt: '',
    body: '',
    is_published: false,
    is_featured: false,
    sort_order: '0',
};

/**
 * The help centre library.
 *
 * Publishing here puts the article on the public help centre and in the client's
 * knowledge base at the same time; the body is markdown and is rendered on the
 * server, so what an operator writes is what a reader sees.
 */
export default function SupportKnowledgeBase({
    articles: rows,
    stats,
}: PageProps) {
    const confirm = useConfirm();
    const [editing, setEditing] = useState<ArticleRow | null>(null);
    const form = useForm<ArticleForm>({ ...BLANK });

    const startCreate = (): void => {
        setEditing(null);
        form.setData({ ...BLANK });
        form.clearErrors();
    };

    const startEdit = (row: ArticleRow): void => {
        setEditing(row);
        form.setData({
            title: row.title,
            category: row.category ?? '',
            excerpt: row.excerpt ?? '',
            body: row.body,
            is_published: row.is_published,
            is_featured: row.is_featured,
            sort_order: String(row.sort_order),
        });
        form.clearErrors();
    };

    const submit = (event?: FormEvent): void => {
        event?.preventDefault();

        if (editing !== null) {
            form.put(articles.update.url(editing.id), {
                preserveScroll: true,
                onSuccess: () => setEditing(null),
            });

            return;
        }

        form.post(articles.store.url(), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    const remove = async (row: ArticleRow): Promise<void> => {
        if (
            !(await confirm({
                title: `Delete “${row.title}”?`,
                description:
                    'The article disappears from the help centre and the knowledge base.',
                confirmLabel: 'Delete article',
                tone: 'danger',
            }))
        ) {
            return;
        }

        form.delete(articles.destroy.url(row.id), { preserveScroll: true });
    };

    return (
        <>
            <Head title="Knowledge base" />

            <div className="grid gap-6">
                <PageHeader
                    icon={Barcode}
                    title="Knowledge base"
                    description="The articles the help centre and the client knowledge base read."
                    actions={
                        <Button variant="outline" onClick={startCreate}>
                            New article
                        </Button>
                    }
                />

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard title="Articles" value={stats.total} tone="brand" />
                    <StatCard
                        title="Published"
                        value={stats.published}
                        tone="success"
                    />
                    <StatCard title="Drafts" value={stats.drafts} tone="neutral" />
                    <StatCard title="Reads" value={stats.views} tone="signal" />
                </div>

                <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_400px]">
                    <Card className="gap-0 overflow-hidden py-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Article</TableHead>
                                    <TableHead>Category</TableHead>
                                    <TableHead>Reads</TableHead>
                                    <TableHead>State</TableHead>
                                    <TableHead />
                                </TableRow>
                            </TableHeader>

                            <TableBody>
                                {rows.map((row) => (
                                    <TableRow key={row.id}>
                                        <TableCell className="max-w-[24rem]">
                                            <p className="font-medium">
                                                {row.title}
                                            </p>
                                            {row.excerpt && (
                                                <p className="truncate text-xs text-muted-foreground">
                                                    {row.excerpt}
                                                </p>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {row.category ?? '—'}
                                        </TableCell>
                                        <TableCell>{row.views}</TableCell>
                                        <TableCell>
                                            {row.is_published ? (
                                                <Badge variant="success">
                                                    Published
                                                </Badge>
                                            ) : (
                                                <Badge variant="muted">
                                                    Draft
                                                </Badge>
                                            )}
                                            {row.is_featured && (
                                                <Badge
                                                    variant="info"
                                                    className="ml-1"
                                                >
                                                    Pinned
                                                </Badge>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right whitespace-nowrap">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => startEdit(row)}
                                            >
                                                <Pencil />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => void remove(row)}
                                            >
                                                <Trash2 />
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>

                        {rows.length === 0 && (
                            <p className="p-8 text-center text-sm text-muted-foreground">
                                No article yet. Write the first one and the help
                                centre stops being empty.
                            </p>
                        )}
                    </Card>
                    <Card className="h-fit">
                        <CardHeader>
                            <CardTitle className="text-sm">
                                {editing === null
                                    ? 'Write an article'
                                    : `Edit “${editing.title}”`}
                            </CardTitle>
                        </CardHeader>

                        <CardContent>
                            <form onSubmit={submit} className="grid gap-3">
                                <div className="grid gap-1">
                                    <Label htmlFor="title">Title</Label>
                                    <Input
                                        id="title"
                                        value={form.data.title}
                                        onChange={(event) =>
                                            form.setData(
                                                'title',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    {form.errors.title && (
                                        <p className="text-xs text-destructive">
                                            {form.errors.title}
                                        </p>
                                    )}
                                </div>

                                <div className="grid gap-1">
                                    <Label htmlFor="category">Category</Label>
                                    <Input
                                        id="category"
                                        value={form.data.category}
                                        placeholder="Getting started"
                                        onChange={(event) =>
                                            form.setData(
                                                'category',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </div>

                                <div className="grid gap-1">
                                    <Label htmlFor="excerpt">
                                        One-line summary
                                    </Label>
                                    <Input
                                        id="excerpt"
                                        value={form.data.excerpt}
                                        onChange={(event) =>
                                            form.setData(
                                                'excerpt',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </div>

                                <div className="grid gap-1">
                                    <Label htmlFor="body">Body (markdown)</Label>
                                    <textarea
                                        id="body"
                                        rows={12}
                                        value={form.data.body}
                                        onChange={(event) =>
                                            form.setData(
                                                'body',
                                                event.target.value,
                                            )
                                        }
                                        className="w-full rounded-md border border-input bg-background px-3 py-2 font-mono text-xs"
                                    />
                                    {form.errors.body && (
                                        <p className="text-xs text-destructive">
                                            {form.errors.body}
                                        </p>
                                    )}
                                </div>

                                <div className="grid gap-1">
                                    <Label htmlFor="sort_order">Order</Label>
                                    <Input
                                        id="sort_order"
                                        type="number"
                                        min={0}
                                        value={form.data.sort_order}
                                        onChange={(event) =>
                                            form.setData(
                                                'sort_order',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </div>
                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={form.data.is_published}
                                        onChange={(event) =>
                                            form.setData(
                                                'is_published',
                                                event.target.checked,
                                            )
                                        }
                                        className="size-4 rounded border-input"
                                    />
                                    Published
                                </label>

                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={form.data.is_featured}
                                        onChange={(event) =>
                                            form.setData(
                                                'is_featured',
                                                event.target.checked,
                                            )
                                        }
                                        className="size-4 rounded border-input"
                                    />
                                    Pin to the top of the library
                                </label>

                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        type="submit"
                                        disabled={form.processing}
                                    >
                                        {editing === null ? 'Create' : 'Save'}
                                    </Button>

                                    {editing !== null && (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            onClick={startCreate}
                                        >
                                            Cancel
                                        </Button>
                                    )}
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}
