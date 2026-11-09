import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, BookOpen, LifeBuoy } from 'lucide-react';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { contact as supportContact } from '@/routes/support';
import { index, show } from '@/routes/support/knowledge-base';
import type { SupportArticleSummary } from '@/types';

type Article = {
    slug: string;
    title: string;
    excerpt: string | null;
    category: string | null;
    html: string;
    updated_at: string | null;
};

type PageProps = {
    article: Article;
    articles: SupportArticleSummary[];
};

/**
 * One knowledge base article.
 *
 * The body arrives as HTML rendered by the server from the stored markdown, so the
 * page is complete before React mounts and a link inside it behaves like any other
 * link in the application.
 */
export default function KnowledgeBaseShow({ article, articles }: PageProps) {
    const others = articles.filter((item) => item.slug !== article.slug).slice(0, 6);

    return (
        <>
            <Head title={article.title} />

            <div className="grid gap-6">
                <PageHeader
                    icon={BookOpen}
                    title={article.title}
                    description={article.excerpt ?? undefined}
                    actions={
                        <Button variant="outline" asChild>
                            <Link href={index()}>
                                <ArrowLeft />
                                All articles
                            </Link>
                        </Button>
                    }
                />

                <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_300px]">
                    <Card>
                        <CardContent>
                            <article
                                className="article-body"
                                // Rendered from the stored markdown by the server;
                                // raw HTML in the markdown is stripped first.
                                dangerouslySetInnerHTML={{ __html: article.html }}
                            />

                            {article.updated_at && (
                                <p className="mt-8 border-t border-border/60 pt-4 text-xs text-muted-foreground">
                                    Last updated{' '}
                                    {new Date(
                                        article.updated_at,
                                    ).toLocaleDateString()}
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    <div className="grid content-start gap-4">
                        <Card className="gap-3 py-4">
                            <CardContent className="px-4 text-sm">
                                <h2 className="text-sm font-semibold">
                                    Still stuck?
                                </h2>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    Open a ticket and the team picks it up with the
                                    context already attached.
                                </p>

                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="mt-3 w-full"
                                    asChild
                                >
                                    <Link href={supportContact()}>
                                        <LifeBuoy />
                                        Contact support
                                    </Link>
                                </Button>
                            </CardContent>
                        </Card>

                        {others.length > 0 && (
                            <Card className="gap-3 py-4">
                                <CardContent className="px-4">
                                    <h2 className="text-sm font-semibold">
                                        More articles
                                    </h2>

                                    <ul className="mt-2 grid gap-2 text-sm">
                                        {others.map((item) => (
                                            <li key={item.slug}>
                                                <Link
                                                    href={show.url(item.slug)}
                                                    className="flex items-start justify-between gap-2 hover:text-brand"
                                                >
                                                    {item.title}
                                                    <ArrowRight className="mt-0.5 size-3.5 shrink-0 text-muted-foreground" />
                                                </Link>
                                            </li>
                                        ))}
                                    </ul>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}
