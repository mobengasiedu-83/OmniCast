import { Head, Link } from '@inertiajs/react';
import { Users } from 'lucide-react';
import AnnouncementController from '@/actions/App/Http/Controllers/Admin/AnnouncementController';
import EmptyState from '@/components/empty-state';
import PageHeader from '@/components/page-header';
import Pagination from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { AnnouncementRecipientRow, Paginated } from '@/types';

type PageProps = {
    announcement: {
        id: number;
        title: string;
        recipient_count: number;
        read_count: number;
    };
    recipients: Paginated<AnnouncementRecipientRow>;
};

const formatDateTime = (value: string | null): string =>
    value ? new Date(value).toLocaleString() : '—';

export default function AnnouncementRecipients({
    announcement,
    recipients,
}: PageProps) {
    return (
        <>
            <Head title={`Recipients · ${announcement.title}`} />
            <PageHeader
                icon={Users}
                title="Announcement recipients"
                description={`“${announcement.title}” · ${announcement.read_count} of ${announcement.recipient_count} opened`}
                actions={
                    <Button asChild variant="outline">
                        <Link href={AnnouncementController.index.url()}>
                            Back to announcements
                        </Link>
                    </Button>
                }
            />

            <Card className="mt-6">
                <CardHeader className="border-b border-border/60 py-3">
                    <CardTitle className="text-sm font-medium">
                        Recipient report
                    </CardTitle>
                    <CardDescription>
                        Accounts reached by the announcement, their delivery
                        channels, and whether they opened it.
                    </CardDescription>
                </CardHeader>
                <CardContent className="pt-0">
                    {recipients.data.length === 0 ? (
                        <EmptyState
                            icon={Users}
                            title="No recipients yet"
                            description="Recipient records appear when announcement delivery reaches an account."
                        />
                    ) : (
                        <div className="divide-y divide-border/60">
                            {recipients.data.map((recipient) => (
                                <article
                                    key={recipient.id}
                                    className="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div className="min-w-0">
                                        <p className="truncate font-medium">
                                            {recipient.name}
                                        </p>
                                        <p className="truncate text-sm text-muted-foreground">
                                            {recipient.email ??
                                                'No email address'}
                                        </p>
                                    </div>

                                    <div className="flex flex-wrap items-center gap-2">
                                        <Badge
                                            variant={
                                                recipient.read_at
                                                    ? 'default'
                                                    : 'muted'
                                            }
                                        >
                                            {recipient.read_at
                                                ? `Opened ${formatDateTime(recipient.read_at)}`
                                                : 'Unread'}
                                        </Badge>
                                        <Badge variant="outline">In-app</Badge>
                                        {recipient.emailed_at && (
                                            <Badge variant="outline">
                                                Emailed{' '}
                                                {formatDateTime(
                                                    recipient.emailed_at,
                                                )}
                                            </Badge>
                                        )}
                                        {recipient.texted_at && (
                                            <Badge variant="outline">
                                                Texted{' '}
                                                {formatDateTime(
                                                    recipient.texted_at,
                                                )}
                                            </Badge>
                                        )}
                                    </div>
                                </article>
                            ))}
                        </div>
                    )}

                    <div className="pt-4">
                        <Pagination paginator={recipients} noun="recipients" />
                    </div>
                </CardContent>
            </Card>
        </>
    );
}
