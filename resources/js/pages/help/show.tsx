import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, BookOpen } from 'lucide-react';
import ContactChannels from '@/components/support/contact-channels';
import { Button } from '@/components/ui/button';
import { show as contactPage } from '@/routes/contact';
import { index, show } from '@/routes/help';
import type { SupportArticleSummary, SupportContact } from '@/types';

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
    related: SupportArticleSummary[];
    contact: SupportContact;
};

/**
 * One help centre article.
 *
 * The body is markdown rendered on the server, and the reading column is the same
 * one the policy pages use, so a link inside an article behaves like a link
 * inside a policy.
 */
export default function HelpShow({ article, related, contact }: PageProps) {
    const others = related
        .filter((item) => item.slug !== article.slug)
        .slice(0, 4);

    return (
        <>
            <Head title={article.title} />

            <div className="grid gap-6 pt-8 lg:grid-cols-[minmax(0,1fr)_300px]">
                <section>
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={index()}>
                            <ArrowLeft />
                            Help centre
                        </Link>
                    </Button>

                    {article.category && (
                        <p className="mt-4 text-[11px] font-semibold tracking-[0.14em] text-brand uppercase">
                            {article.category}
                        </p>
                    )}

                    <h1 className="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">
                        {article.title}
                    </h1>

                    {article.excerpt && (
                        <p className="mt-2 text-sm text-muted-foreground">
                            {article.excerpt}
                        </p>
                    )}

                    <article
                        className="article-body mt-6"
                        // Rendered from the stored markdown by the server; raw HTML
                        // in the markdown is stripped before it reaches the page.
                        dangerouslySetInnerHTML={{ __html: article.html }}
                    />

                    {article.updated_at && (
                        <p className="mt-8 border-t border-border/60 pt-4 text-xs text-muted-foreground">
                            Last updated{' '}
                            {new Date(article.updated_at).toLocaleDateString()}
                        </p>
                    )}
                </section>

                <aside className="grid content-start gap-4">
                    <section className="rounded-2xl border border-border/70 bg-muted/30 p-5">
                        <h2 className="flex items-center gap-2 text-sm font-semibold tracking-tight">
                            <BookOpen className="size-4 text-brand" />
                            More articles
                        </h2>

                        <ul className="mt-3 grid gap-2 text-sm">
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

                            {others.length === 0 && (
                                <li className="text-xs text-muted-foreground">
                                    It is the only published article so far.
                                </li>
                            )}
                        </ul>
                    </section>

                    <section className="rounded-2xl border border-border/70 bg-card/70 p-5">
                        <h2 className="text-sm font-semibold tracking-tight">
                            Did this answer it?
                        </h2>
                        <p className="mt-1 text-xs text-muted-foreground">
                            If not, write to the team and quote what you were
                            doing when it went wrong.
                        </p>

                        <Button className="mt-3 w-full" asChild>
                            <Link href={contactPage()}>Contact us</Link>
                        </Button>

                        <ContactChannels
                            contact={contact}
                            className="mt-4 text-sm"
                        />
                    </section>
                </aside>
            </div>
        </>
    );
}
