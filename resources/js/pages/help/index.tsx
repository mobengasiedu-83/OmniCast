import { Form, Head, Link } from '@inertiajs/react';
import { BookOpen, Search } from 'lucide-react';
import { useState } from 'react';
import ArticleList from '@/components/support/article-list';
import ContactChannels from '@/components/support/contact-channels';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { show as contactPage } from '@/routes/contact';
import { index, show } from '@/routes/help';
import type { SupportArticleSummary, SupportContact } from '@/types';

type PageProps = {
    articles: SupportArticleSummary[];
    categories: string[];
    filters: { search: string };
    contact: SupportContact;
};

/**
 * The public help centre.
 *
 * One search box and the whole published library, grouped by the categories the
 * operator set. An article a visitor reads here is the same one a client reads
 * inside the application, so an answer never has two versions.
 */
export default function HelpIndex({
    articles,
    categories,
    filters,
    contact,
}: PageProps) {
    const [active, setActive] = useState<string>('all');

    const visible =
        active === 'all'
            ? articles
            : articles.filter((article) => article.category === active);

    return (
        <>
            <Head title="Help centre" />

            <section className="mx-auto max-w-3xl py-12 text-center">
                <span className="inline-flex items-center gap-2 rounded-full bg-brand-soft px-3 py-1 text-xs font-medium text-accent-foreground ring-1 ring-brand/20">
                    <BookOpen className="size-3.5" />
                    Help centre
                </span>

                <h1 className="mt-5 text-3xl font-semibold tracking-tight sm:text-4xl">
                    Answers before you ask
                </h1>

                <p className="mt-3 text-sm text-muted-foreground">
                    Campaigns, sender IDs, wallets, reporting and the API — the
                    guides the team would otherwise send you.
                </p>

                <Form
                    {...index.form()}
                    className="mx-auto mt-6 flex max-w-xl gap-2"
                >
                    {({ processing }) => (
                        <>
                            <Input
                                name="search"
                                defaultValue={filters.search}
                                placeholder="Search the help centre"
                                aria-label="Search the help centre"
                            />
                            <Button type="submit" disabled={processing}>
                                <Search />
                                Search
                            </Button>
                        </>
                    )}
                </Form>
            </section>

            {categories.length > 0 && (
                <div className="mb-6 flex flex-wrap items-center justify-center gap-2">
                    <Button
                        variant={active === 'all' ? 'default' : 'outline'}
                        size="sm"
                        onClick={() => setActive('all')}
                    >
                        All articles
                    </Button>

                    {categories.map((category) => (
                        <Button
                            key={category}
                            variant={active === category ? 'default' : 'outline'}
                            size="sm"
                            onClick={() => setActive(category)}
                        >
                            {category}
                        </Button>
                    ))}
                </div>
            )}

            <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_300px]">
                <ArticleList
                    articles={visible}
                    href={show.url}
                    empty={
                        <>
                            Nothing matches that search. Try a shorter phrase, or{' '}
                            <Link href={contactPage()} className="text-brand">
                                ask the team
                            </Link>
                            .
                        </>
                    }
                />
                <HelpSidebar contact={contact} />
            </div>
        </>
    );
}


/**
 * The "still stuck" panel, reused by both help centre pages.
 */
export function HelpSidebar({ contact }: { contact: SupportContact }) {
    return (
        <aside className="grid content-start gap-4">
            <section className="rounded-2xl border border-border/70 bg-muted/30 p-5">
                <h2 className="text-sm font-semibold tracking-tight">
                    Still stuck?
                </h2>
                <p className="mt-1 text-xs text-muted-foreground">
                    Send the team the reference of the campaign or transaction you
                    are asking about.
                </p>

                <Button className="mt-3 w-full" asChild>
                    <Link href={contactPage()}>Contact us</Link>
                </Button>

                <ContactChannels contact={contact} className="mt-4 text-sm" />
            </section>
        </aside>
    );
}
