import { Head, Link, router, useForm } from '@inertiajs/react';
import { CalendarClock, Megaphone, Send, Trash2 } from 'lucide-react';
import { useConfirm } from '@/components/confirm-dialog';
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
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AnnouncementController from '@/actions/App/Http/Controllers/Admin/AnnouncementController';
import { Textarea } from '@/components/ui/textarea';
import { urls } from '@/lib/urls';
import type {
    AnnouncementAudienceOption,
    AnnouncementRow,
    AnnouncementSeverityOption,
    ClientOption,
    NotificationCategoryOption,
    Paginated,
    SelectOption,
} from '@/types';

type PageProps = {
    announcements: Paginated<AnnouncementRow>;
    filters: { status: string | null };
    stats: {
        drafts: number;
        scheduled: number;
        sent: number;
        recipients: number;
        read: number;
    };
    audiences: AnnouncementAudienceOption[];
    categories: NotificationCategoryOption[];
    severities: AnnouncementSeverityOption[];
    roles: SelectOption[];
    clients: ClientOption[];
    smsEnabled: boolean;
};

const formatDateTime = (value: string | null): string =>
    value ? new Date(value).toLocaleString() : '—';

/**
 * The platform messaging centre.
 *
 * The composer writes one announcement and the history below it shows what went
 * out to whom. Delivery is queued, so a message addressed to every tenant never
 * holds the request that saved it.
 */
export default function AnnouncementsIndex({
    announcements,
    filters,
    stats,
    audiences,
    categories,
    severities,
    roles,
    clients,
    smsEnabled,
}: PageProps) {
    const confirm = useConfirm();

    const remove = async (announcement: AnnouncementRow): Promise<void> => {
        const confirmed = await confirm({
            title: `Delete "${announcement.title}"?`,
            description:
                'The announcement and its read receipts are deleted. Notifications already in inboxes are kept.',
            confirmLabel: 'Delete announcement',
            tone: 'danger',
        });

        if (confirmed) {
            router.delete(urls.admin.announcements.destroy(announcement.id), {
                preserveScroll: true,
            });
        }
    };

    return (
        <>
            <Head title="Announcements" />
            <PageHeader
                icon={Megaphone}
                title="Announcements"
                description="Compose one message and deliver it to an audience of accounts — everyone, the platform team, every tenant, one tenant or one role."
                actions={
                    <Button asChild variant="outline">
                        <Link href={urls.notifications.index}>
                            Open my notifications
                        </Link>
                    </Button>
                }
            />

            <div className="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <Stat label="Drafts waiting" value={stats.drafts} />
                <Stat label="Scheduled" value={stats.scheduled} />
                <Stat label="Sent" value={stats.sent} />
                <Stat label="Recipients reached" value={stats.recipients} />
                <Stat label="Opened" value={stats.read} />
            </div>

            <div className="mt-4 flex flex-wrap items-center gap-2">
                {(
                    [
                        ['', 'All announcements'],
                        ['draft', 'Drafts'],
                        ['scheduled', 'Scheduled'],
                        ['sent', 'Sent'],
                    ] as const
                ).map(([value, label]) => (
                    <Button
                        key={label}
                        asChild
                        size="sm"
                        variant={
                            (filters.status ?? '') === value
                                ? 'default'
                                : 'outline'
                        }
                    >
                        <Link
                            href={
                                value === ''
                                    ? urls.admin.announcements.index
                                    : `${urls.admin.announcements.index}?status=${value}`
                            }
                        >
                            {label}
                        </Link>
                    </Button>
                ))}
            </div>

            <div className="mt-6 grid items-start gap-6 xl:grid-cols-2">
                <AnnouncementComposer
                    audiences={audiences}
                    categories={categories}
                    severities={severities}
                    roles={roles}
                    clients={clients}
                    smsEnabled={smsEnabled}
                />

                <Card>
                    <CardHeader className="border-b border-border/60 py-3">
                        <CardTitle className="text-sm font-medium">
                            History
                        </CardTitle>
                        <CardDescription>
                            Every announcement, newest first, with how much of
                            the audience opened it.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="pt-0">
                        {announcements.data.length === 0 ? (
                            <EmptyState
                                icon={Megaphone}
                                title="Nothing announced yet"
                                description="Compose the first announcement on the left. It stays a draft until you send it."
                            />
                        ) : (
                            <div className="divide-y divide-border/60">
                                {announcements.data.map((announcement) => (
                                    <AnnouncementHistoryRow
                                        key={announcement.id}
                                        announcement={announcement}
                                        onDelete={() =>
                                            void remove(announcement)
                                        }
                                    />
                                ))}
                            </div>
                        )}

                        <div className="pt-4">
                            <Pagination
                                paginator={announcements}
                                noun="announcements"
                            />
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

/** One headline number for the stats strip. */
function Stat({ label, value }: { label: string; value: number }) {
    return (
        <div className="rounded-lg border p-4">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="mt-1 text-2xl font-semibold tracking-tight">
                {value.toLocaleString()}
            </p>
        </div>
    );
}

type ComposerData = {
    title: string;
    body: string;
    category: string;
    audience: string;
    client_id: string;
    role: string;
    severity: string;
    send_email: boolean;
    send_sms: boolean;
    send_now: boolean;
    scheduled_at: string;
};

const emptyComposer: ComposerData = {
    title: '',
    body: '',
    category: 'platform',
    audience: 'everyone',
    client_id: '',
    role: '',
    severity: 'info',
    send_email: false,
    send_sms: false,
    send_now: true,
    scheduled_at: '',
};

/**
 * The composer: one announcement, the audience it addresses, when it goes out
 * and which channel copies should follow the inbox row.
 */
function AnnouncementComposer({
    audiences,
    categories,
    severities,
    roles,
    clients,
    smsEnabled,
}: Pick<
    PageProps,
    'audiences' | 'categories' | 'severities' | 'roles' | 'clients'
> & { smsEnabled: boolean }) {
    const form = useForm<ComposerData>({ ...emptyComposer });

    const audience = audiences.find(
        (option) => option.value === form.data.audience,
    );

    const submit = (event: React.FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        form.post(urls.admin.announcements.store, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <Card>
            <CardHeader className="border-b border-border/60 py-3">
                <CardTitle className="text-sm font-medium">Compose</CardTitle>
                <CardDescription>
                    Saved as a draft unless you leave "deliver immediately"
                    ticked. Recipients are resolved when the message goes out.
                </CardDescription>
            </CardHeader>
            <CardContent className="pt-4">
                <form className="space-y-4" onSubmit={submit}>
                    <div className="space-y-1.5">
                        <Label htmlFor="announcement-title">Title</Label>
                        <Input
                            id="announcement-title"
                            value={form.data.title}
                            maxLength={120}
                            placeholder="Scheduled maintenance on Saturday"
                            onChange={(event) =>
                                form.setData('title', event.target.value)
                            }
                        />
                        {form.errors.title && (
                            <p className="text-xs text-destructive">
                                {form.errors.title}
                            </p>
                        )}
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="announcement-body">Message</Label>
                        <Textarea
                            id="announcement-body"
                            value={form.data.body}
                            rows={5}
                            maxLength={4000}
                            placeholder="What do you want every recipient to know?"
                            onChange={(event) =>
                                form.setData('body', event.target.value)
                            }
                        />
                        {form.errors.body && (
                            <p className="text-xs text-destructive">
                                {form.errors.body}
                            </p>
                        )}
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-1.5">
                            <Label>Category</Label>
                            <Select
                                value={form.data.category}
                                onValueChange={(value) =>
                                    form.setData('category', value)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {categories.map((category) => (
                                        <SelectItem
                                            key={category.value}
                                            value={category.value}
                                        >
                                            {category.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="space-y-1.5">
                            <Label>Severity</Label>
                            <Select
                                value={form.data.severity}
                                onValueChange={(value) =>
                                    form.setData('severity', value)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {severities.map((severity) => (
                                        <SelectItem
                                            key={severity.value}
                                            value={severity.value}
                                        >
                                            {severity.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div className="space-y-1.5">
                        <Label>Audience</Label>
                        <Select
                            value={form.data.audience}
                            onValueChange={(value) =>
                                form.setData('audience', value)
                            }
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {audiences.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {audience && (
                            <p className="text-xs text-muted-foreground">
                                {audience.description}
                            </p>
                        )}
                        {form.errors.audience && (
                            <p className="text-xs text-destructive">
                                {form.errors.audience}
                            </p>
                        )}
                    </div>

                    {form.data.audience === 'client' && (
                        <div className="space-y-1.5">
                            <Label>Client account</Label>
                            <Select
                                value={form.data.client_id}
                                onValueChange={(value) =>
                                    form.setData('client_id', value)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Choose a client" />
                                </SelectTrigger>
                                <SelectContent>
                                    {clients.map((client) => (
                                        <SelectItem
                                            key={client.id}
                                            value={String(client.id)}
                                        >
                                            {client.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {form.errors.client_id && (
                                <p className="text-xs text-destructive">
                                    {form.errors.client_id}
                                </p>
                            )}
                        </div>
                    )}

                    {form.data.audience === 'role' && (
                        <div className="space-y-1.5">
                            <Label>Role</Label>
                            <Select
                                value={form.data.role}
                                onValueChange={(value) =>
                                    form.setData('role', value)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Choose a role" />
                                </SelectTrigger>
                                <SelectContent>
                                    {roles.map((role) => (
                                        <SelectItem
                                            key={role.value}
                                            value={role.value}
                                        >
                                            {role.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {form.errors.role && (
                                <p className="text-xs text-destructive">
                                    {form.errors.role}
                                </p>
                            )}
                        </div>
                    )}

                    <div className="space-y-2 rounded-lg border p-3">
                        <label className="flex items-start gap-2 text-sm">
                            <Checkbox
                                checked={form.data.send_email}
                                onCheckedChange={(value) =>
                                    form.setData('send_email', value === true)
                                }
                            />
                            <span>
                                Also email recipients
                                <span className="block text-xs text-muted-foreground">
                                    Only accounts with the email channel on for
                                    this category receive the email copy.
                                </span>
                            </span>
                        </label>

                        {smsEnabled && (
                            <label className="flex items-start gap-2 text-sm">
                                <Checkbox
                                    checked={form.data.send_sms}
                                    onCheckedChange={(value) =>
                                        form.setData('send_sms', value === true)
                                    }
                                />
                                <span>
                                    Also text recipients
                                    <span className="block text-xs text-muted-foreground">
                                        Only accounts with the SMS channel on
                                        for this category and a phone number
                                        receive the text copy.
                                    </span>
                                </span>
                            </label>
                        )}

                        <label className="flex items-start gap-2 text-sm">
                            <Checkbox
                                checked={form.data.send_now}
                                onCheckedChange={(value) =>
                                    form.setData('send_now', value === true)
                                }
                            />
                            <span>
                                Deliver immediately
                                <span className="block text-xs text-muted-foreground">
                                    Leave this off to keep the message as a
                                    draft, or to hold it until the send time
                                    below.
                                </span>
                            </span>
                        </label>
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="announcement-scheduled-at">
                            Send at (optional)
                        </Label>
                        <Input
                            id="announcement-scheduled-at"
                            type="datetime-local"
                            value={form.data.scheduled_at}
                            disabled={form.data.send_now}
                            onChange={(event) => {
                                form.setData(
                                    'scheduled_at',
                                    event.target.value,
                                );

                                if (event.target.value !== '') {
                                    form.setData('send_now', false);
                                }
                            }}
                        />
                        <p className="text-xs text-muted-foreground">
                            {form.data.send_now
                                ? 'Clear "deliver immediately" to pick a send time.'
                                : 'The announcement is queued when this time arrives. Leave it empty to keep a draft.'}
                        </p>
                        {form.errors.scheduled_at && (
                            <p className="text-xs text-destructive">
                                {form.errors.scheduled_at}
                            </p>
                        )}
                    </div>

                    <Button type="submit" disabled={form.processing}>
                        <Send />
                        {form.processing
                            ? 'Working…'
                            : form.data.send_now
                              ? 'Send announcement'
                              : form.data.scheduled_at !== ''
                                ? 'Schedule announcement'
                                : 'Save draft'}
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}

/** The badge tone names the backend sends are exactly the Badge variants. */
const toneVariant = (
    tone: string,
):
    | 'default'
    | 'secondary'
    | 'destructive'
    | 'outline'
    | 'success'
    | 'warning'
    | 'info'
    | 'muted' =>
    tone as
        | 'default'
        | 'secondary'
        | 'destructive'
        | 'outline'
        | 'success'
        | 'warning'
        | 'info'
        | 'muted';

/** One announcement in the history, with its read-through and actions. */
function AnnouncementHistoryRow({
    announcement,
    onDelete,
}: {
    announcement: AnnouncementRow;
    onDelete: () => void;
}) {
    const openedShare =
        announcement.recipient_count === 0
            ? 0
            : Math.round(
                  (announcement.read_count / announcement.recipient_count) *
                      100,
              );

    return (
        <article className="space-y-2 py-4">
            <div className="flex flex-wrap items-center gap-2">
                <Badge variant={toneVariant(announcement.status_tone)}>
                    {announcement.status_label}
                </Badge>
                <Badge variant={toneVariant(announcement.severity_tone)}>
                    {announcement.severity_label}
                </Badge>
                <Badge variant="muted">{announcement.category_label}</Badge>
                <span className="text-xs text-muted-foreground">
                    {announcement.audience_label}
                </span>
            </div>

            <div>
                <p className="font-medium">{announcement.title}</p>
                <p className="line-clamp-2 text-sm text-muted-foreground">
                    {announcement.body}
                </p>
            </div>

            <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="text-xs text-muted-foreground">
                    {announcement.status === 'sent'
                        ? `${announcement.recipient_count} recipient(s) · ${announcement.read_count} opened (${openedShare}%) · sent ${formatDateTime(announcement.sent_at)}`
                        : announcement.status === 'scheduled'
                          ? `Scheduled for ${formatDateTime(announcement.scheduled_at)} by ${announcement.author ?? 'the platform team'}`
                          : `Draft by ${announcement.author ?? 'the platform team'} · created ${formatDateTime(announcement.created_at)}`}
                    {announcement.send_email && ' · email copy requested'}
                    {announcement.send_sms && ' · SMS copy requested'}
                </p>

                <div className="flex items-center gap-1">
                    {announcement.status === 'sent' && (
                        <Button asChild size="sm" variant="outline">
                            <Link
                                href={AnnouncementController.recipients.url({
                                    announcement: announcement.id,
                                })}
                            >
                                View recipients
                            </Link>
                        </Button>
                    )}

                    {announcement.status === 'scheduled' && (
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() =>
                                router.post(
                                    urls.admin.announcements.cancelSchedule(
                                        announcement.id,
                                    ),
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <CalendarClock />
                            Cancel schedule
                        </Button>
                    )}

                    {announcement.status !== 'sent' && (
                        <Button
                            size="sm"
                            variant="secondary"
                            onClick={() =>
                                router.post(
                                    urls.admin.announcements.send(
                                        announcement.id,
                                    ),
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <Send />
                            Send now
                        </Button>
                    )}

                    <Button
                        size="icon"
                        variant="ghost"
                        aria-label="Delete announcement"
                        onClick={onDelete}
                    >
                        <Trash2 />
                    </Button>
                </div>
            </div>
        </article>
    );
}
