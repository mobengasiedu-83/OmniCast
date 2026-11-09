import { Form, Head, Link } from '@inertiajs/react';
import { BookOpen, LifeBuoy, Search } from 'lucide-react';
import { useState } from 'react';
import PageHeader from '@/components/page-header';
import ArticleList from '@/components/support/article-list';
import ContactChannels from '@/components/support/contact-channels';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { contact as supportContact } from '@/routes/support';
import { index, show } from '@/routes/support/knowledge-base';
import type { SupportArticleSummary, SupportContact } from '@/types';

type PageProps = {
    articles: SupportArticleSummary[];
    categories: string[];
    filters: { search: string };
    contact: SupportContact;
};

/**
 * The knowledge base inside the application.
 *
 * Same articles as the public help centre, read under the app chrome so a client
 * does not lose the campaign they were working on to look something up.
 */
export default function KnowledgeBaseIndex({
    articles,
    categories,
    filters,
    contact,
}: PageProps) {
    const [active, setActive] = useState('all');

    const visible =
        active === 'all'
            ? articles
            : articles.filter((article) => article.category === active);

    return (
        <>
            <Head title="Knowledge base" />

            <div className="grid gap-6">
                <PageHeader
                    icon={BookOpen}
                    title="Knowledge base"
                    description="Guides for campaigns, sender IDs, wallets, reporting and the API — the answers the support team would send you."
                    actions={
                        <Button variant="outline" asChild>
                            <Link href={supportContact()}>
                                <LifeBuoy />
                                Contact support
                            </Link>
                        </Button>
                    }
                />

                <Form
                    {...index.form()}
                    className="flex max-w-xl flex-wrap gap-2"
                >
                    {({ processing }) => (
                        <>
                            <Input
                                name="search"
                                defaultValue={filters.search}
                                placeholder="Search the knowledge base"
                                aria-label="Search the knowledge base"
                                className="min-w-0 flex-1"
                            />
                            <Button type="submit" disabled={processing}>
                                <Search />
                                Search
                            </Button>
                        </>
                    )}
                </Form>

                <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_300px]">
                    <div className="grid gap-4">
                        {categories.length > 0 && (
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    variant={
                                        active === 'all' ? 'default' : 'outline'
                                    }
                                    size="sm"
                                    onClick={() => setActive('all')}
                                >
                                    All
                                </Button>

                                {categories.map((category) => (
                                    <Button
                                        key={category}
                                        variant={
                                            active === category
                                                ? 'default'
                                                : 'outline'
                                        }
                                        size="sm"
                                        onClick={() => setActive(category)}
                                    >
                                        {category}
                                    </Button>
                                ))}
                            </div>
                        )}

                        <ArticleList
                            articles={visible}
                            href={show.url}
                            empty="Nothing matches that search. Try a shorter phrase, or open a ticket."
                        />
                    </div>

                    <Card className="h-fit gap-3 py-4">
                        <CardContent className="px-4 text-sm">
                            <h2 className="text-sm font-semibold">
                                Did not find it?
                            </h2>
                            <p className="mt-1 text-xs text-muted-foreground">
                                The team answers tickets in the order they arrive,
                                and urgent delivery problems first.
                            </p>

                            <Button
                                variant="outline"
                                size="sm"
                                className="mt-3 w-full"
                                asChild
                            >
                                <Link href={supportContact()}>
                                    Contact support
                                </Link>
                            </Button>

                            <ContactChannels
                                contact={contact}
                                className="mt-4 text-sm"
                            />
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}
