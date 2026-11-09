import { Head, Link } from '@inertiajs/react';
import { BookOpen, LifeBuoy, Mail, PlusCircle } from 'lucide-react';
import PageHeader from '@/components/page-header';
import ContactChannels from '@/components/support/contact-channels';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    index as knowledgeBase,
    show as knowledgeBaseArticle,
} from '@/routes/support/knowledge-base';
import { create as createTicket } from '@/routes/support/tickets';
import type {
    SupportArticleSummary,
    SupportCategoryOption,
    SupportContact,
} from '@/types';

type PageProps = {
    categories: SupportCategoryOption[];
    contact: SupportContact;
    articles: SupportArticleSummary[];
};

/**
 * Contact support, from inside the application.
 *
 * This is a directory rather than a second form: a client who wants a trackable
 * conversation opens a ticket, and a client who wants to talk now is shown the
 * confirmed telephone, WhatsApp and email of the team that answers.
 */
export default function ContactSupport({
    categories,
    contact,
    articles,
}: PageProps) {
    return (
        <>
            <Head title="Contact support" />

            <div className="grid gap-6">
                <PageHeader
                    icon={LifeBuoy}
                    title="Contact support"
                    description="Open a ticket so the conversation is tracked, or reach the team directly if it cannot wait."
                    actions={
                        <Button asChild>
                            <Link href={createTicket()}>
                                <PlusCircle />
                                New ticket
                            </Link>
                        </Button>
                    }
                />

                <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm">
                                Who answers what
                            </CardTitle>
                        </CardHeader>

                        <CardContent>
                            <ul className="grid gap-3">
                                {categories.map((category) => (
                                    <li
                                        key={category.slug}
                                        className="rounded-xl border border-border/70 bg-card/60 p-4"
                                    >
                                        <p className="text-sm font-semibold">
                                            {category.name}
                                        </p>

                                        {category.description && (
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {category.description}
                                            </p>
                                        )}

                                        <p className="mt-2 text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                                            Typical priority:{' '}
                                            {category.priority_label}
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>

                    <div className="grid content-start gap-4">
                        <Card className="gap-3 py-4">
                            <CardHeader className="px-4">
                                <CardTitle className="flex items-center gap-2 text-sm">
                                    <Mail className="size-4 text-brand" />
                                    Reach the team
                                </CardTitle>
                            </CardHeader>

                            <CardContent className="px-4">
                                <ContactChannels
                                    contact={contact}
                                    className="text-sm"
                                />

                                {!contact.support_email &&
                                    !contact.phone &&
                                    !contact.whatsapp && (
                                        <p className="text-xs text-muted-foreground">
                                            The published contact details are
                                            being confirmed. A ticket reaches the
                                            team today.
                                        </p>
                                    )}
                            </CardContent>
                        </Card>

                        <Card className="gap-3 py-4">
                            <CardHeader className="px-4">
                                <CardTitle className="flex items-center gap-2 text-sm">
                                    <BookOpen className="size-4 text-brand" />
                                    Before you write
                                </CardTitle>
                            </CardHeader>

                            <CardContent className="px-4">
                                <ul className="grid gap-2 text-sm">
                                    {articles.map((article) => (
                                        <li key={article.slug}>
                                            <Link
                                                href={knowledgeBaseArticle.url(
                                                    article.slug,
                                                )}
                                                className="hover:text-brand"
                                            >
                                                {article.title}
                                            </Link>
                                        </li>
                                    ))}
                                </ul>

                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="mt-3 w-full"
                                    asChild
                                >
                                    <Link href={knowledgeBase()}>
                                        Browse the knowledge base
                                    </Link>
                                </Button>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}
