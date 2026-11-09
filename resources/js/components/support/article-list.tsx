import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import type { ReactNode } from 'react';
import type { SupportArticleSummary } from '@/types';

/**
 * A published library, grouped by the category the operator set.
 *
 * The public help centre and the signed-in knowledge base render the same list,
 * so an article added in the admin appears in both without a second screen.
 */
export default function ArticleList({
    articles,
    href,
    empty,
}: {
    articles: SupportArticleSummary[];
    href: (slug: string) => string;
    empty?: ReactNode;
}) {
    const grouped = articles.reduce<Record<string, SupportArticleSummary[]>>(
        (groups, article) => {
            const key = article.category ?? 'Other';

            groups[key] = [...(groups[key] ?? []), article];

            return groups;
        },
        {},
    );

    if (articles.length === 0) {
        return (
            <p className="rounded-xl border border-border/70 p-8 text-center text-sm text-muted-foreground">
                {empty ?? 'No articles have been published yet.'}
            </p>
        );
    }

    return (
        <div className="grid gap-8">
            {Object.entries(grouped).map(([category, items]) => (
                <section key={category}>
                    <h2 className="text-xs font-semibold tracking-[0.14em] text-muted-foreground uppercase">
                        {category}
                    </h2>

                    <div className="mt-3 grid gap-3">
                        {items.map((article) => (
                            <Link
                                key={article.slug}
                                href={href(article.slug)}
                                className="group rounded-xl border border-border/70 bg-card/60 p-4 transition-colors hover:border-brand/40"
                            >
                                <p className="flex items-center justify-between gap-3 text-sm font-semibold">
                                    {article.title}
                                    <ArrowRight className="size-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5 group-hover:text-brand" />
                                </p>

                                {article.excerpt && (
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {article.excerpt}
                                    </p>
                                )}
                            </Link>
                        ))}
                    </div>
                </section>
            ))}
        </div>
    );
}
