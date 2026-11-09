import { Form, Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, ExternalLink, Lock, Paperclip, Send } from 'lucide-react';
import PageHeader from '@/components/page-header';
import StatusBadge from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import enquiries from '@/routes/admin/support/enquiries';
import tickets from '@/routes/admin/support/tickets';
import type { SupportAttachmentLimits, SupportOption } from '@/types';

type Attachment = { id: number; name: string; size: string; url: string };

type Message = {
    id: number;
    author_name: string;
    author_email: string | null;
    from_agent: boolean;
    is_internal: boolean;
    body: string;
    created_at: string | null;
    attachments: Attachment[];
};

type Ticket = {
    reference: string;
    type: string;
    type_label: string;
    is_ticket: boolean;
    subject: string;
    description: string;
    status: string;
    status_label: string;
    priority: string;
    priority_label: string;
    name: string;
    email: string;
    phone: string | null;
    company: string | null;
    category: string | null;
    category_id: number | null;
    team: string | null;
    team_id: number | null;
    assignee: string | null;
    assigned_to: number | null;
    client: string | null;
    client_code: string | null;
    campaign: { name: string; reference: string; status: string } | null;
    transaction: {
        description: string;
        amount: string;
        reference: string | null;
        created_at: string | null;
    } | null;
    ip_address: string | null;
    consent_at: string | null;
    created_at: string | null;
    first_responded_at: string | null;
    resolved_at: string | null;
    closed_at: string | null;
    last_reply_at: string | null;
};

type PageProps = {
    ticket: Ticket;
    messages: Message[];
    statuses: SupportOption[];
    priorities: SupportOption[];
    teams: SupportOption[];
    categories: SupportOption[];
    agents: SupportOption[];
    attachments: SupportAttachmentLimits;
    can_manage: boolean;
};

/**
 * One support conversation, as an agent works it.
 *
 * Three controls do the work: a reply (or an internal note a colleague reads but
 * the requester never sees), the owner and team, and the status. Everything the
 * requester told us — including the campaign or wallet movement they picked — is
 * on the screen, so the first reply does not have to ask for it again.
 */
export default function AdminSupportTicket({
    ticket,
    messages,
    statuses,
    priorities,
    teams,
    categories,
    agents,
    attachments,
    can_manage,
}: PageProps) {
    const reply = ticket.is_ticket
        ? tickets.reply.form(ticket.reference)
        : enquiries.reply.form(ticket.reference);

    const updateUrl = ticket.is_ticket
        ? tickets.update.url(ticket.reference)
        : enquiries.update.url(ticket.reference);

    const form = useForm({
        status: ticket.status,
        priority: ticket.priority,
        support_team_id: ticket.team_id === null ? '' : String(ticket.team_id),
        support_category_id:
            ticket.category_id === null ? '' : String(ticket.category_id),
        assigned_to:
            ticket.assigned_to === null ? '' : String(ticket.assigned_to),
    });

    const save = (): void => {
        form.put(updateUrl, { preserveScroll: true });
    };

    return (
        <>
            <Head title={`${ticket.reference} · ${ticket.subject}`} />

            <div className="grid gap-6">
                <PageHeader
                    title={ticket.subject}
                    badge={
                        <>
                            <StatusBadge
                                status={ticket.status}
                                label={ticket.status_label}
                            />
                            <Badge variant="outline">{ticket.type_label}</Badge>
                        </>
                    }
                    description={`${ticket.reference} · ${ticket.name}${ticket.company ? ` (${ticket.company})` : ''} · opened ${ticket.created_at ? new Date(ticket.created_at).toLocaleString() : ''}`}
                    actions={
                        <Button variant="outline" asChild>
                            <Link
                                href={
                                    ticket.is_ticket
                                        ? tickets.index()
                                        : enquiries.index()
                                }
                            >
                                <ArrowLeft />
                                Back to the queue
                            </Link>
                        </Button>
                    }
                />

                <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_340px]">
                    <div className="grid gap-4">
                        <Card className="gap-0 overflow-hidden py-0">
                            <CardHeader className="border-b border-border/60 py-4">
                                <CardTitle className="text-sm">
                                    Conversation ({messages.length})
                                </CardTitle>
                            </CardHeader>

                            <CardContent className="p-0">
                                <ul className="divide-y divide-border/60">
                                    {messages.map((message) => (
                                        <li
                                            key={message.id}
                                            className={
                                                message.is_internal
                                                    ? 'bg-warning-soft/40 p-4'
                                                    : message.from_agent
                                                      ? 'bg-brand-soft/30 p-4'
                                                      : 'p-4'
                                            }
                                        >
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="text-sm font-semibold">
                                                    {message.author_name}
                                                </span>

                                                {message.from_agent && (
                                                    <Badge variant="info">
                                                        Agent
                                                    </Badge>
                                                )}

                                                {message.is_internal && (
                                                    <Badge variant="warning">
                                                        <Lock className="size-3" />
                                                        Internal note
                                                    </Badge>
                                                )}

                                                <span className="text-xs text-muted-foreground">
                                                    {message.author_email}
                                                </span>

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
                                    Reply to the requester
                                </CardTitle>
                                <p className="text-xs text-muted-foreground">
                                    Tick “internal note” to write to the team
                                    instead: the requester never sees it and no
                                    email is sent.
                                </p>
                            </CardHeader>

                            <CardContent>
                                <Form
                                    {...reply}
                                    encType="multipart/form-data"
                                    resetOnSuccess
                                    className="grid gap-3"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <textarea
                                                name="body"
                                                required
                                                rows={5}
                                                aria-label="Reply"
                                                placeholder="Answer the question, or say what you need next."
                                                className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                            />
                                            {errors.body && (
                                                <p className="text-xs text-destructive">
                                                    {errors.body}
                                                </p>
                                            )}

                                            <div className="grid gap-2">
                                                <Label htmlFor="reply-attachments">
                                                    Attach files
                                                </Label>
                                                <input
                                                    id="reply-attachments"
                                                    name="attachments[]"
                                                    type="file"
                                                    multiple
                                                    accept={attachments.mimes
                                                        .map(
                                                            (mime) =>
                                                                `.${mime}`,
                                                        )
                                                        .join(',')}
                                                    className="block w-full rounded-md border border-input bg-background px-3 py-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-muted file:px-2 file:py-1 file:text-xs"
                                                />
                                            </div>

                                            <label className="flex items-center gap-2 text-xs text-muted-foreground">
                                                <input
                                                    type="checkbox"
                                                    name="internal"
                                                    value="1"
                                                    className="size-4 rounded border-input"
                                                />
                                                Internal note (not sent to the
                                                requester)
                                            </label>

                                            <div>
                                                <Button
                                                    type="submit"
                                                    disabled={
                                                        processing || !can_manage
                                                    }
                                                >
                                                    <Send />
                                                    Send
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
                                <CardTitle className="text-sm">Requester</CardTitle>
                            </CardHeader>

                            <CardContent className="grid gap-1 px-4 text-sm">
                                <p className="font-medium">{ticket.name}</p>
                                <a
                                    href={`mailto:${ticket.email}`}
                                    className="text-xs text-brand hover:underline"
                                >
                                    {ticket.email}
                                </a>
                                {ticket.phone && (
                                    <p className="text-xs text-muted-foreground">
                                        {ticket.phone}
                                    </p>
                                )}
                                {ticket.company && (
                                    <p className="text-xs text-muted-foreground">
                                        {ticket.company}
                                    </p>
                                )}
                                {ticket.client && (
                                    <p className="text-xs text-muted-foreground">
                                        Client: {ticket.client}
                                        {ticket.client_code
                                            ? ` (${ticket.client_code})`
                                            : ''}
                                    </p>
                                )}
                                {ticket.ip_address && (
                                    <p className="text-[11px] text-muted-foreground">
                                        Submitted from {ticket.ip_address}
                                    </p>
                                )}
                            </CardContent>
                        </Card>

                        {ticket.campaign && (
                            <Card className="gap-3 py-4">
                                <CardHeader className="px-4">
                                    <CardTitle className="text-sm">
                                        Related campaign
                                    </CardTitle>
                                </CardHeader>

                                <CardContent className="px-4 text-sm">
                                    <p className="font-medium">
                                        {ticket.campaign.name}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {ticket.campaign.reference} ·{' '}
                                        {ticket.campaign.status}
                                    </p>
                                </CardContent>
                            </Card>
                        )}

                        {ticket.transaction && (
                            <Card className="gap-3 py-4">
                                <CardHeader className="px-4">
                                    <CardTitle className="text-sm">
                                        Related wallet movement
                                    </CardTitle>
                                </CardHeader>

                                <CardContent className="px-4 text-sm">
                                    <p className="font-medium">
                                        {ticket.transaction.description}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {ticket.transaction.amount}
                                        {ticket.transaction.reference
                                            ? ` · ${ticket.transaction.reference}`
                                            : ''}
                                    </p>
                                </CardContent>
                            </Card>
                        )}

                        <Card className="gap-3 py-4">
                            <CardHeader className="px-4">
                                <CardTitle className="text-sm">
                                    Routing and status
                                </CardTitle>
                            </CardHeader>

                            <CardContent className="grid gap-3 px-4">
                                <RoutingSelect
                                    id="status"
                                    label="Status"
                                    value={form.data.status}
                                    options={statuses}
                                    onChange={(value) =>
                                        form.setData('status', value)
                                    }
                                />
                                <RoutingSelect
                                    id="priority"
                                    label="Priority"
                                    value={form.data.priority}
                                    options={priorities}
                                    onChange={(value) =>
                                        form.setData('priority', value)
                                    }
                                />
                                <RoutingSelect
                                    id="support_team_id"
                                    label="Team"
                                    value={form.data.support_team_id}
                                    options={teams}
                                    emptyLabel="No team"
                                    onChange={(value) =>
                                        form.setData('support_team_id', value)
                                    }
                                />
                                <RoutingSelect
                                    id="support_category_id"
                                    label="Category"
                                    value={form.data.support_category_id}
                                    options={categories}
                                    emptyLabel="No category"
                                    onChange={(value) =>
                                        form.setData(
                                            'support_category_id',
                                            value,
                                        )
                                    }
                                />
                                <RoutingSelect
                                    id="assigned_to"
                                    label="Owner"
                                    value={form.data.assigned_to}
                                    options={agents}
                                    emptyLabel="Unassigned"
                                    onChange={(value) =>
                                        form.setData('assigned_to', value)
                                    }
                                />

                                <Button
                                    type="button"
                                    onClick={save}
                                    disabled={form.processing || !can_manage}
                                >
                                    Save changes
                                </Button>

                                {!can_manage && (
                                    <p className="text-[11px] text-muted-foreground">
                                        Your role can read the register but not
                                        change it.
                                    </p>
                                )}
                            </CardContent>
                        </Card>
                    </aside>
                </div>
            </div>
        </>
    );
}

/**
 * One labelled select in the routing panel.
 */
function RoutingSelect({
    id,
    label,
    value,
    options,
    onChange,
    emptyLabel,
}: {
    id: string;
    label: string;
    value: string;
    options: SupportOption[];
    onChange: (value: string) => void;
    emptyLabel?: string;
}) {
    return (
        <div className="grid gap-1">
            <Label htmlFor={id} className="text-xs text-muted-foreground">
                {label}
            </Label>
            <select
                id={id}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
            >
                {emptyLabel !== undefined && (
                    <option value="">{emptyLabel}</option>
                )}
                {options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
        </div>
    );
}
