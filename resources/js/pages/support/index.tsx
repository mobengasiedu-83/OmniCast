import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    BookOpen,
    Inbox,
    LifeBuoy,
    MessagesSquare,
    PlusCircle,
} from 'lucide-react';
import ArticleList from '@/components/support/article-list';
import ContactChannels from '@/components/support/contact-channels';
import PageHeader from '@/components/page-header';
import StatCard from '@/components/stat-card';
import StatusBadge from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { contact as supportContact } from '@/routes/support';
import {
    index as knowledgeBase,
    show as knowledgeBaseArticle,
} from '@/routes/support/knowledge-base';
import tickets from '@/routes/support/tickets';
import type {
    SupportArticleSummary,
    SupportCategoryOption,
    SupportContact,
} from '@/types';

type TicketRow = {
    reference: string;
    subject: string;
    status: string;
    status_label: string;
    category: string | null;
    waiting_since: string;
    url: string;
};

type PageProps = {
    stats: {
        open: number;
        awaiting_customer: number;
        total: number;
        resolved: number;
    };
    tickets: TicketRow[];
    categories: SupportCategoryOption[];
    articles: SupportArticleSummary[];
    contact: SupportContact;
};

/**
 * The support overview a signed-in client lands on.
 *
 * Three answers in one screen: what is open, how to reach a person, and whether an
 * article already covers it — so the first click is usually the right one.
 */
export default function SupportOverview({
    stats,
    tickets: recent,
    articles,
    contact,
}: PageProps) {
    return (
        <>
            <Head title="Support" />

            <div className="grid gap-6">
                <PageHeader
                    icon={LifeBuoy}
                    title="Support"
                    description="Open a ticket the team can track, reply to one already open, or find the answer yourself."
                    actions={
                        <Button asChild>
                            <Link href={tickets.create()}>
                                <PlusCircle />
                                New ticket
                            </Link>
                        </Button>
                    }
                />

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        title="Open"
                        value={stats.open}
                        icon={Inbox}
                        tone="warning"
                        description="Waiting on us or on you"
                    />
                    <StatCard
                        title="Awaiting you"
                        value={stats.awaiting_customer}
                        icon={MessagesSquare}
                        tone="signal"
                        description="We asked a question"
                    />
                    <StatCard
                        title="Resolved"
                        value={stats.resolved}
                        icon={LifeBuoy}
                        tone="success"
                        description="Closed tickets"
                    />
                    <StatCard
                        title="All tickets"
                        value={stats.total}
                        icon={BookOpen}
                        tone="neutral"
                        description="Everything opened"
                    />
                </div>
            </div>

            <div className="mt-6 grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
                <Card className="gap-0 py-0">
                    <CardHeader className="flex-row items-center justify-between gap-3 border-b border-border/60 py-4">
                        <CardTitle className="text-sm">Recent tickets</CardTitle>
                        <Button variant="ghost" size="sm" asChild>
                            <Link href={tickets.index()}>
                                All tickets
                                <ArrowRight />
                            </Link>
                        </Button>
                    </CardHeader>

                    <CardContent className="p-0">
                        {recent.length === 0 ? (
                            <div className="p-8 text-center text-sm text-muted-foreground">
                                <p>No ticket yet.</p>
                                <p className="mt-1 text-xs">
                                    A ticket keeps the whole conversation in one
                                    place, with a reference you can quote.
                                </p>
                                <Button size="sm" className="mt-3" asChild>
                                    <Link href={tickets.create()}>
                                        Open a ticket
                                    </Link>
                                </Button>
                            </div>
                        ) : (
                            <ul className="divide-y divide-border/60">
                                {recent.map((ticket) => (
                                    <li key={ticket.reference}>
                                        <Link
                                            href={ticket.url}
                                            className="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm transition-colors hover:bg-muted/40"
                                        >
                                            <span className="min-w-0">
                                                <span className="block truncate font-medium">
                                                    {ticket.subject}
                                                </span>
                                                <span className="text-xs text-muted-foreground">
                                                    {ticket.reference}
                                                    {ticket.category
                                                        ? ` · ${ticket.category}`
                                                        : ''}{' '}
                                                    · {ticket.waiting_since}
                                                </span>
                                            </span>

                                            <StatusBadge
                                                status={ticket.status}
                                                label={ticket.status_label}
                                            />
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>

                <div className="grid content-start gap-4">
                    <Card className="gap-3 py-4">
                        <CardHeader className="px-4">
                            <CardTitle className="text-sm">
                                Reach a person
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="px-4">
                            <ContactChannels
                                contact={contact}
                                className="text-sm"
                            />

                            <Button
                                variant="outline"
                                size="sm"
                                className="mt-3 w-full"
                                asChild
                            >
                                <Link href={supportContact()}>
                                    All contact options
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>

                    <Card className="gap-3 py-4">
                        <CardHeader className="flex-row items-center justify-between px-4">
                            <CardTitle className="text-sm">
                                Popular answers
                            </CardTitle>
                            <Button variant="ghost" size="sm" asChild>
                                <Link href={knowledgeBase()}>
                                    <BookOpen />
                                </Link>
                            </Button>
                        </CardHeader>
                        <CardContent className="px-4">
                            <ArticleList
                                articles={articles}
                                href={(slug) =>
                                    knowledgeBaseArticle.url(slug)
                                }
                                empty="The library is still being written."
                            />
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}
