import { Head, useForm } from '@inertiajs/react';
import { BookOpen, Pencil, Trash2 } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { useConfirm } from '@/components/confirm-dialog';
import PageHeader from '@/components/page-header';
import StatCard from '@/components/stat-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import postsRoute from '@/routes/admin/blog';

type BlogPostRow = {
    id: number; title: string; slug: string; category: string | null;
    excerpt: string | null; body: string; meta_title: string | null;
    meta_description: string | null; status: 'draft' | 'published';
    is_featured: boolean; sort_order: number; published_at: string | null;
};
type BlogPostForm = {
    title: string; slug: string; category: string; excerpt: string; body: string;
    meta_title: string; meta_description: string; status: 'draft' | 'published';
    is_featured: boolean; sort_order: string;
};
const BLANK: BlogPostForm = {
    title: '', slug: '', category: '', excerpt: '', body: '', meta_title: '',
    meta_description: '', status: 'draft', is_featured: false, sort_order: '0',
};

export default function BlogAdmin({ posts, stats }: { posts: BlogPostRow[]; stats: { total: number; published: number; drafts: number } }) {
    const confirm = useConfirm();
    const [editing, setEditing] = useState<BlogPostRow | null>(null);
    const form = useForm<BlogPostForm>({ ...BLANK });
    const createNew = (): void => { setEditing(null); form.setData({ ...BLANK }); form.clearErrors(); };
    const edit = (post: BlogPostRow): void => {
        setEditing(post);
        form.setData({ title: post.title, slug: post.slug, category: post.category ?? '', excerpt: post.excerpt ?? '', body: post.body, meta_title: post.meta_title ?? '', meta_description: post.meta_description ?? '', status: post.status, is_featured: post.is_featured, sort_order: String(post.sort_order) });
        form.clearErrors();
    };
    const submit = (event: FormEvent): void => {
        event.preventDefault();
        if (editing) {
            form.put(postsRoute.update.url(editing.id), { preserveScroll: true, onSuccess: () => setEditing(null) });
        } else {
            form.post(postsRoute.store.url(), { preserveScroll: true, onSuccess: () => form.reset() });
        }
    };
    const remove = async (post: BlogPostRow): Promise<void> => {
        if (await confirm({ title: `Delete “${post.title}”?`, description: 'This permanently removes the article from the blog.', confirmLabel: 'Delete article', tone: 'danger' })) {
            form.delete(postsRoute.destroy.url(post.id), { preserveScroll: true });
        }
    };
    const fieldError = (field: keyof BlogPostForm) => form.errors[field] ? <p className="text-xs text-destructive">{form.errors[field]}</p> : null;

    return <>
        <Head title="Blog articles" />
        <div className="grid gap-6">
            <PageHeader icon={BookOpen} title="Blog articles" description="Write, edit and publish public articles. Drafts stay private until you publish them." actions={<Button variant="outline" onClick={createNew}>New article</Button>} />
            <div className="grid gap-3 sm:grid-cols-3">
                <StatCard title="Articles" value={stats.total} tone="brand" />
                <StatCard title="Published" value={stats.published} tone="success" />
                <StatCard title="Drafts" value={stats.drafts} tone="neutral" />
            </div>
            <div className="grid items-start gap-4 xl:grid-cols-[minmax(0,1fr)_440px]">
                <Card className="gap-0 overflow-hidden py-0">
                    <Table><TableHeader><TableRow><TableHead>Article</TableHead><TableHead>State</TableHead><TableHead /></TableRow></TableHeader>
                        <TableBody>{posts.map((post) => <TableRow key={post.id}>
                            <TableCell className="max-w-[28rem]"><p className="font-medium">{post.title}</p><p className="truncate text-xs text-muted-foreground">/blog/{post.slug}</p></TableCell>
                            <TableCell><Badge variant={post.status === 'published' ? 'success' : 'muted'}>{post.status === 'published' ? 'Published' : 'Draft'}</Badge>{post.is_featured && <Badge variant="info" className="ml-1">Featured</Badge>}</TableCell>
                            <TableCell className="text-right whitespace-nowrap"><Button variant="ghost" size="sm" onClick={() => edit(post)} aria-label={`Edit ${post.title}`}><Pencil /></Button><Button variant="ghost" size="sm" onClick={() => void remove(post)} aria-label={`Delete ${post.title}`}><Trash2 /></Button></TableCell>
                        </TableRow>)}</TableBody>
                    </Table>
                    {posts.length === 0 && <p className="p-8 text-center text-sm text-muted-foreground">No blog articles yet. Create a draft to get started.</p>}
                </Card>
                <Card className="h-fit">
                    <CardHeader><CardTitle className="text-sm">{editing ? `Edit “${editing.title}”` : 'Write an article'}</CardTitle></CardHeader>
                    <CardContent><form onSubmit={submit} className="grid gap-3">
                        <div className="grid gap-1"><Label htmlFor="post-title">Title</Label><Input id="post-title" value={form.data.title} onChange={(event) => form.setData('title', event.target.value)} />{fieldError('title')}</div>
                        <div className="grid gap-1"><Label htmlFor="post-slug">URL slug</Label><Input id="post-slug" value={form.data.slug} placeholder="Generated from title when empty" onChange={(event) => form.setData('slug', event.target.value)} />{fieldError('slug')}</div>
                        <div className="grid gap-1"><Label htmlFor="post-category">Category</Label><Input id="post-category" value={form.data.category} onChange={(event) => form.setData('category', event.target.value)} />{fieldError('category')}</div>
                        <div className="grid gap-1"><Label htmlFor="post-excerpt">Summary</Label><Textarea id="post-excerpt" rows={2} value={form.data.excerpt} onChange={(event) => form.setData('excerpt', event.target.value)} />{fieldError('excerpt')}</div>
                        <div className="grid gap-1"><Label htmlFor="post-body">Article body (Markdown)</Label><Textarea id="post-body" rows={12} value={form.data.body} onChange={(event) => form.setData('body', event.target.value)} />{fieldError('body')}<p className="text-xs text-muted-foreground">Headings, lists, links and tables are supported. HTML is stripped when displayed.</p></div>
                        <div className="grid gap-1"><Label htmlFor="post-meta-title">SEO title</Label><Input id="post-meta-title" value={form.data.meta_title} onChange={(event) => form.setData('meta_title', event.target.value)} />{fieldError('meta_title')}</div>
                        <div className="grid gap-1"><Label htmlFor="post-meta-description">SEO description</Label><Textarea id="post-meta-description" rows={2} value={form.data.meta_description} onChange={(event) => form.setData('meta_description', event.target.value)} />{fieldError('meta_description')}</div>
                        <div className="grid gap-1"><Label htmlFor="post-status">Status</Label><select id="post-status" value={form.data.status} onChange={(event) => form.setData('status', event.target.value as BlogPostForm['status'])} className="h-9 rounded-md border border-input bg-background px-3 text-sm"><option value="draft">Draft</option><option value="published">Published</option></select>{fieldError('status')}</div>
                        <div className="grid gap-1"><Label htmlFor="post-order">Display order</Label><Input id="post-order" type="number" min="0" value={form.data.sort_order} onChange={(event) => form.setData('sort_order', event.target.value)} />{fieldError('sort_order')}</div>
                        <label className="flex items-center gap-2 text-sm"><Checkbox checked={form.data.is_featured} onCheckedChange={(checked) => form.setData('is_featured', checked === true)} />Feature this article</label>
                        {fieldError('is_featured')}
                        <div className="flex gap-2 pt-2"><Button type="submit" disabled={form.processing}>{form.processing ? 'Saving…' : editing ? 'Save changes' : 'Create article'}</Button>{editing && <Button type="button" variant="ghost" onClick={createNew}>Cancel</Button>}</div>
                    </form></CardContent>
                </Card>
            </div>
        </div>
    </>;
}
