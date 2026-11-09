import { Form, Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    CheckCircle2,
    Clock,
    ExternalLink,
    Paperclip,
    Send,
} from 'lucide-react';
import PageHeader from '@/components/page-header';
import StatusBadge from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { close, index, reply } from '@/routes/support/tickets';
import { contact as supportContact } from '@/routes/support';
import type { SupportAttachmentLimits, SupportContact } from '@/types';

type Attachment = {
    id: number;
    name: string;
    size: string;
    url: string;
};

type Message = {
    id: number;
    author_name: string;
    from_agent: boolean;
    body: string;
    created_at: string | null;
    attachments: Attachment[];
};

type Ticket = {
    reference: string;
    subject: string;
    description: string;
    status: string;
    status_label: string;
    priority: string;
    priority_label: string;
    category: string | null;
    team: string | null;
    assignee: string | null;
    waiting_since: string;
    created_at: string | null;
    last_reply_at: string | null;
    first_responded_at: string | null;
    resolved_at: string | null;
    closed_at: string | null;
    campaign: { name: string; reference: string } | null;
    transaction: { description: string; amount: string; reference: string | null } | null;
    url: string;
};

type PageProps = {
    ticket: Ticket;
    messages: Message[];
    can_reply: boolean;
    attachments: SupportAttachmentLimits;
    contact: SupportContact;
};

/**
 * One ticket, read as a conversation.
 *
 * The requester sees every message except the agents' internal notes — those never
 * leave the platform. Replying to a settled ticket reopens it, so the thread stays
 * the single history of the problem.
 */
export default function TicketShow({
    ticket,
    messages,
    can_reply,
    attachments,
}: PageProps) {
    return (
        <>
            <Head title={ticket.subject} />

            <div className="grid gap-6">
                <PageHeader
                    icon={Clock}
                    title={ticket.subject}
                    badge={
                        <StatusBadge
                            status={ticket.status}
                            label={ticket.status_label}
                        />
                    }
                    description={`${ticket.reference}${ticket.category ? ` · ${ticket.category}` : ''} · opened ${ticket.waiting_since}`}
                    actions={
                        <Button variant="outline" asChild>
                            <Link href={index()}>
                                <ArrowLeft />
                                My tickets
                            </Link>
                        </Button>
                    }
                />

                <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_300px]">
                    <div className="grid gap-4">
                        <Card className="gap-0 overflow-hidden py-0">
                            <CardHeader className="border-b border-border/60 py-4">
                                <CardTitle className="text-sm">
                                    Conversation
                                </CardTitle>
                            </CardHeader>

                            <CardContent className="p-0">
                                <ul className="divide-y divide-border/60">
                                    {messages.map((message) => (
                                        <li
                                            key={message.id}
                                            className={`p-4 ${
                                                message.from_agent
                                                    ? 'bg-brand-soft/40'
                                                    : ''
                                            }`}
                                        >
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="text-sm font-semibold">
                                                    {message.from_agent
                                                        ? 'Support team'
                                                        : message.author_name}
                                                </span>

                                                {message.from_agent && (
                                                    <span className="rounded-full bg-brand px-2 py-0.5 text-[10px] font-semibold tracking-wide text-primary-foreground uppercase">
                                                        Agent
                                                    </span>
                                                )}

                                                <span className="text-xs text-muted-foreground">
                                                    {message.created_at
                                                        ? new Date(
                                                              message.created_at,
                                                          ).toLocaleString()
                                                        : ''}
                                                </span>
                                            </div>

                                            <p className="mt-2 text-sm whitespace-pre-line">
                                                {message.body}
                                            </p>

                                            {message.attachments.length > 0 && (
                                                <ul className="mt-2 grid gap-1">
                                                    {message.attachments.map(
                                                        (attachment) => (
                                                            <li
                                                                key={
                                                                    attachment.id
                                                                }
                                                            >
                                                                <a
                                                                    href={
                                                                        attachment.url
                                                                    }
                                                                    className="inline-flex items-center gap-1.5 text-xs text-brand hover:underline"
                                                                >
                                                                    <Paperclip className="size-3" />
                                                                    {
                                                                        attachment.name
                                                                    }{' '}
                                                                    (
                                                                    {
                                                                        attachment.size
                                                                    }
                                                                    )
                                                                </a>
                                                            </li>
                                                        ),
                                                    )}
                                                </ul>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-sm">
                                    Add a reply
                                </CardTitle>
                                {!can_reply && (
                                    <p className="text-xs text-muted-foreground">
                                        This ticket is settled. Replying reopens
                                        it, so the whole history stays together.
                                    </p>
                                )}
                            </CardHeader>

                            <CardContent>
                                <Form
                                    {...reply.form(ticket.reference)}
                                    encType="multipart/form-data"
                                    resetOnSuccess
                                    className="grid gap-3"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <div className="grid gap-2">
                                                <Label
                                                    htmlFor="body"
                                                    className="sr-only"
                                                >
                                                    Message
                                                </Label>
                                                <textarea
                                                    id="body"
                                                    name="body"
                                                    required
                                                    rows={4}
                                                    placeholder="Add anything the team should know."
                                                    className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                                />
                                                {errors.body && (
                                                    <p className="text-xs text-destructive">
                                                        {errors.body}
                                                    </p>
                                                )}
                                            </div>

                                            <input
                                                name="attachments[]"
                                                type="file"
                                                multiple
                                                accept={attachments.mimes
                                                    .map((mime) => `.${mime}`)
                                                    .join(',')}
                                                className="block w-full rounded-md border border-input bg-background px-3 py-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-muted file:px-2 file:py-1 file:text-xs"
                                            />

                                            <div>
                                                <Button
                                                    type="submit"
                                                    disabled={processing}
                                                >
                                                    <Send />
                                                    Send reply
                                                </Button>
                                            </div>
                                        </>
                                    )}
                                </Form>
                            </CardContent>
                        </Card>
                    </div>

                    <aside className="grid content-start gap-4">
                        <Card className="gap-3 py-4">
                            <CardHeader className="px-4">
                                <CardTitle className="text-sm">Details</CardTitle>
                            </CardHeader>

                            <CardContent className="grid gap-2 px-4 text-sm">
                                <Detail label="Reference" value={ticket.reference} />
                                <Detail label="Status" value={ticket.status_label} />
                                <Detail label="Priority" value={ticket.priority_label} />
                                <Detail label="Team" value={ticket.team ?? '—'} />
                                <Detail
                                    label="Owner"
                                    value={ticket.assignee ?? 'Unassigned'}
                                />

                                {ticket.campaign && (
                                    <Detail
                                        label="Campaign"
                                        value={`${ticket.campaign.name} · ${ticket.campaign.reference}`}
                                    />
                                )}

                                {ticket.transaction && (
                                    <Detail
                                        label="Wallet movement"
                                        value={`${ticket.transaction.description} · ${ticket.transaction.amount}`}
                                    />
                                )}
                            </CardContent>
                        </Card>

                        {ticket.status !== 'closed' && (
                            <Card className="gap-3 py-4">
                                <CardHeader className="px-4">
                                    <CardTitle className="text-sm">
                                        Problem solved?
                                    </CardTitle>
                                </CardHeader>

                                <CardContent className="px-4">
                                    <p className="text-xs text-muted-foreground">
                                        Closing the ticket tells the team no
                                        further action is needed. You can reply to
                                        it later if the problem returns.
                                    </p>

                                    <Form
                                        {...close.form(ticket.reference)}
                                        className="mt-3"
                                    >
                                        {({ processing }) => (
                                            <Button
                                                type="submit"
                                                variant="outline"
                                                size="sm"
                                                className="w-full"
                                                disabled={processing}
                                            >
                                                <CheckCircle2 />
                                                Close ticket
                                            </Button>
                                        )}
                                    </Form>
                                </CardContent>
                            </Card>
                        )}

                        <Card className="gap-3 py-4">
                            <CardHeader className="px-4">
                                <CardTitle className="text-sm">
                                    Prefer to talk?
                                </CardTitle>
                            </CardHeader>

                            <CardContent className="px-4 text-sm">
                                <p className="text-xs text-muted-foreground">
                                    Quote {ticket.reference} so the team can pull
                                    up this thread.
                                </p>

                                <Link
                                    href={supportContact()}
                                    className="mt-2 inline-flex items-center gap-1.5 text-xs font-medium text-brand hover:underline"
                                >
                                    Contact options
                                    <ExternalLink className="size-3" />
                                </Link>
                            </CardContent>
                        </Card>
                    </aside>
                </div>
            </div>
        </>
    );
}

/**
 * One label/value row in the details card.
 */
function Detail({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex items-start justify-between gap-3">
            <span className="text-xs text-muted-foreground">{label}</span>
            <span className="text-right text-sm font-medium">{value}</span>
        </div>
    );
}
