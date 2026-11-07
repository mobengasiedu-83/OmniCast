import { Link, router, useForm } from '@inertiajs/react';
import { Eye, Save, Trash2 } from 'lucide-react';
import { type FormEvent, useMemo } from 'react';
import { useConfirm } from '@/components/confirm-dialog';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type {
    ClientOption,
    SelectOption,
    TemplateCategoryOption,
    TemplateFormValues,
} from '@/types';

/**
 * The longest copy the server accepts on each channel, mirrored here so the
 * counter stops the author before the request does.
 */
const bodyLimits: Record<string, number> = {
    sms: 1600,
    multi_channel: 1600,
    voice: 4000,
    email: 100000,
};

const defaults: TemplateFormValues = {
    name: '',
    channel: 'sms',
    template_category_id: '',
    language: 'en',
    description: '',
    subject: '',
    body: '',
    status: 'draft',
    client_id: '',
};

/**
 * Every merge tag the copy currently uses, in the order they first appear.
 */
function variableTags(body: string): string[] {
    const matches = body.matchAll(/{{\s*([a-zA-Z0-9_]+)\s*}}/g);
    const tags = new Set<string>();

    for (const match of matches) {
        tags.add(match[1]);
    }

    return [...tags];
}

/**
 * How the copy reads once the tags are filled in: a segment count for SMS, a
 * word count for email, so the author can judge the length before saving.
 */
function renderPreview(
    body: string,
    channel: string,
): { text: string; note: string } {
    const text = body.replace(
        /{{\s*([a-zA-Z0-9_]+)\s*}}/g,
        (_match, tag: string) => `[${tag.replace(/_/g, ' ')}]`,
    );

    if (channel === 'email') {
        const words = text.split(/\s+/).filter(Boolean).length;

        return { text, note: `${words} ${words === 1 ? 'word' : 'words'}` };
    }

    if (channel === 'voice') {
        return { text, note: `${text.length} characters of spoken script` };
    }

    const segments = text.length <= 160 ? 1 : Math.ceil(text.length / 153);

    return {
        text,
        note: `${text.length} characters · ${segments} ${segments === 1 ? 'segment' : 'segments'}`,
    };
}

export default function TemplateForm({
    action,
    method,
    categories,
    channels,
    statuses,
    clients = [],
    initial,
    submitLabel,
    cancelHref,
    deleteUrl,
    catalogue = false,
}: {
    action: string;
    method: 'post' | 'put';
    categories: TemplateCategoryOption[];
    channels: SelectOption[];
    statuses: SelectOption[];
    clients?: ClientOption[];
    initial?: Partial<TemplateFormValues>;
    submitLabel: string;
    cancelHref: string;
    deleteUrl?: string;
    catalogue?: boolean;
}) {
    const form = useForm<TemplateFormValues>({ ...defaults, ...initial });
    const confirm = useConfirm();

    const limit = bodyLimits[form.data.channel] ?? 1600;
    const needsSubject = form.data.channel === 'email';
    const variables = useMemo(
        () => variableTags(form.data.body),
        [form.data.body],
    );
    const preview = useMemo(
        () =>
            renderPreview(
                form.data.body || 'Your copy appears here.',
                form.data.channel,
            ),
        [form.data.body, form.data.channel],
    );

    const send = (): void => {
        if (method === 'put') {
            form.put(action);

            return;
        }

        form.post(action);
    };

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        if (form.data.status !== 'pending') {
            send();

            return;
        }

        void confirm({
            title: `Submit "${form.data.name.trim() || 'this template'}" for review?`,
            description:
                'The platform reviews the copy before approving it. The template stays editable while it waits, but only active templates can be picked in the campaign editor.',
            confirmLabel: 'Submit for review',
            cancelLabel: 'Keep editing',
        }).then((confirmed) => {
            if (confirmed) {
                send();
            }
        });
    };

    const destroy = async (): Promise<void> => {
        if (!deleteUrl) {
            return;
        }

        const confirmed = await confirm({
            title: `Remove "${form.data.name}"?`,
            description:
                'The template disappears from the library. Campaigns that already used its copy are not affected.',
            confirmLabel: 'Remove template',
            cancelLabel: 'Keep template',
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        router.delete(deleteUrl, { preserveScroll: true });
    };

    const changeChannel = (value: string): void => {
        form.setData((data) => ({
            ...data,
            channel: value,
            subject: value === 'email' ? data.subject : '',
        }));
    };

    return (
        <form onSubmit={submit} className="grid gap-4 lg:grid-cols-3">
            <div className="flex flex-col gap-4 lg:col-span-2">
                <Card>
                    <CardHeader>
                        <CardTitle>
                            {catalogue ? 'Catalogue template' : 'Template'}
                        </CardTitle>
                        <CardDescription>
                            {catalogue
                                ? 'Every client sees a catalogue template. Editing it here changes the starting point for everyone.'
                                : 'Name the template, pick the channel it writes for and add the copy a campaign starts from.'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-1.5 sm:col-span-2">
                            <Label htmlFor="name">Template name</Label>
                            <Input
                                id="name"
                                value={form.data.name}
                                maxLength={120}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                                placeholder="Payment due reminder"
                                autoComplete="off"
                            />
                            <InputError message={form.errors.name} />
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="channel">Channel</Label>
                            <Select
                                value={form.data.channel}
                                onValueChange={changeChannel}
                            >
                                <SelectTrigger id="channel" className="w-full">
                                    <SelectValue placeholder="Pick a channel" />
                                </SelectTrigger>
                                <SelectContent>
                                    {channels.map((channel) => (
                                        <SelectItem
                                            key={channel.value}
                                            value={channel.value}
                                        >
                                            {channel.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.channel} />
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="category">Category</Label>
                            <Select
                                value={form.data.template_category_id || 'none'}
                                onValueChange={(value) =>
                                    form.setData(
                                        'template_category_id',
                                        value === 'none' ? '' : value,
                                    )
                                }
                            >
                                <SelectTrigger id="category" className="w-full">
                                    <SelectValue placeholder="No category" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">
                                        No category
                                    </SelectItem>
                                    {categories.map((category) => (
                                        <SelectItem
                                            key={category.id}
                                            value={String(category.id)}
                                        >
                                            {category.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError
                                message={form.errors.template_category_id}
                            />
                        </div>

                        {clients.length > 0 && (
                            <div className="grid gap-1.5">
                                <Label htmlFor="client_id">Client</Label>
                                <Select
                                    value={form.data.client_id || 'none'}
                                    onValueChange={(value) =>
                                        form.setData(
                                            'client_id',
                                            value === 'none' ? '' : value,
                                        )
                                    }
                                >
                                    <SelectTrigger
                                        id="client_id"
                                        className="w-full"
                                    >
                                        <SelectValue placeholder="Choose a client" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">
                                            Choose a client
                                        </SelectItem>
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
                                <InputError message={form.errors.client_id} />
                            </div>
                        )}

                        <div className="grid gap-1.5">
                            <Label htmlFor="language">Language</Label>
                            <Input
                                id="language"
                                value={form.data.language}
                                maxLength={2}
                                onChange={(event) =>
                                    form.setData(
                                        'language',
                                        event.target.value.toLowerCase(),
                                    )
                                }
                                placeholder="en"
                            />
                            <p className="text-xs text-muted-foreground">
                                Two-letter ISO code the copy is written in.
                            </p>
                            <InputError message={form.errors.language} />
                        </div>

                        <div className="grid gap-1.5 sm:col-span-2">
                            <Label htmlFor="description">Description</Label>
                            <Input
                                id="description"
                                value={form.data.description}
                                maxLength={255}
                                onChange={(event) =>
                                    form.setData(
                                        'description',
                                        event.target.value,
                                    )
                                }
                                placeholder="When an operator should reach for this template."
                            />
                            <InputError message={form.errors.description} />
                        </div>

                        {needsSubject && (
                            <div className="grid gap-1.5 sm:col-span-2">
                                <Label htmlFor="subject">Subject line</Label>
                                <Input
                                    id="subject"
                                    value={form.data.subject}
                                    maxLength={255}
                                    onChange={(event) =>
                                        form.setData(
                                            'subject',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Your payment is due on {{due_date}}"
                                />
                                <InputError message={form.errors.subject} />
                            </div>
                        )}

                        <div className="grid gap-1.5 sm:col-span-2">
                            <div className="flex items-end justify-between gap-2">
                                <Label htmlFor="body">Message copy</Label>
                                <span className="text-xs text-muted-foreground tabular-nums">
                                    {form.data.body.length}/{limit}
                                </span>
                            </div>
                            <Textarea
                                id="body"
                                rows={7}
                                value={form.data.body}
                                maxLength={limit}
                                onChange={(event) =>
                                    form.setData('body', event.target.value)
                                }
                                placeholder="Hi {{first_name}}, your payment of GHS {{amount}} is due on {{due_date}}."
                            />
                            <p className="text-xs text-muted-foreground">
                                Wrap merge tags in double braces, for example{' '}
                                <code className="rounded bg-muted px-1">
                                    {'{{first_name}}'}
                                </code>
                                . The campaign editor fills them per contact.
                            </p>
                            <InputError message={form.errors.body} />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-sm">
                            <Eye className="size-4 text-muted-foreground" />
                            How it reads
                        </CardTitle>
                        <CardDescription>{preview.note}</CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3">
                        {needsSubject && form.data.subject && (
                            <p className="text-sm font-semibold">
                                {form.data.subject}
                            </p>
                        )}
                        <p className="text-sm whitespace-pre-wrap text-muted-foreground">
                            {preview.text}
                        </p>
                        {variables.length > 0 && (
                            <div className="flex flex-wrap gap-1.5">
                                {variables.map((variable) => (
                                    <span
                                        key={variable}
                                        className="rounded-md bg-muted px-1.5 py-0.5 font-mono text-[11px] text-muted-foreground"
                                    >
                                        {variable}
                                    </span>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            <div className="flex flex-col gap-4">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-sm">Publishing</CardTitle>
                        <CardDescription>
                            Drafts stay inside the library until you publish
                            them, and review asks the platform to sign the copy
                            off before operators rely on it.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3">
                        <div className="grid gap-1.5">
                            <Label htmlFor="status">Status</Label>
                            <Select
                                value={form.data.status}
                                onValueChange={(value) =>
                                    form.setData('status', value)
                                }
                            >
                                <SelectTrigger id="status" className="w-full">
                                    <SelectValue placeholder="Draft" />
                                </SelectTrigger>
                                <SelectContent>
                                    {statuses.map((status) => (
                                        <SelectItem
                                            key={status.value}
                                            value={status.value}
                                        >
                                            {status.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.status} />
                        </div>

                        <div className="flex flex-col gap-2">
                            <Button
                                type="submit"
                                disabled={form.processing}
                                className="w-full"
                            >
                                <Save />
                                {form.processing ? 'Saving…' : submitLabel}
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                asChild
                                className="w-full"
                            >
                                <Link href={cancelHref}>Cancel</Link>
                            </Button>
                            {deleteUrl && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    className="w-full text-destructive hover:text-destructive"
                                    onClick={() => void destroy()}
                                >
                                    <Trash2 />
                                    Remove template
                                </Button>
                            )}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </form>
    );
}
