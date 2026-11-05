import { Link, useForm } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
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
import { urls } from '@/lib/urls';
import type { CampaignFormOptions, CampaignFormValues } from '@/types';

const defaults: CampaignFormValues = {
    name: '',
    channel: 'sms',
    contact_list_id: '',
    contact_segment_id: '',
    sender_id: '',
    caller_id: '',
    sms_message: '',
    email_subject: '',
    email_body: '',
    email_format: 'html',
    voice_script: '',
    voice_max_duration_seconds: '30',
    ivr_enabled: '0',
    ivr_prompt: '',
    ivr_timeout_seconds: '6',
    ivr_max_attempts: '3',
    ivr_options: {},
    schedule_type: 'now',
    scheduled_at: '',
    timezone: 'Africa/Accra',
    max_retries: '3',
    retry_delay_minutes: '15',
    notes: '',
    client_id: '',
    idempotency_key: '',
};

/**
 * The digits a campaign may map onto an action, in the order the switch reads
 * them. The opt-out digit is added by the platform and cannot be remapped.
 */
const ivrDigits = ['1', '2', '3', '4'];

const ivrActions = [
    { value: 'connect', label: 'Play the message' },
    { value: 'repeat', label: 'Replay the prompt' },
    { value: 'callback', label: 'Request a callback' },
    { value: 'opt_out', label: 'Opt the recipient out' },
];

/**
 * A key that stays stable for this form mount, so a retried submit cannot
 * create a second draft.
 */
function newIdempotencyKey(): string {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }

    return `key-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 10)}`;
}

export default function CampaignForm({
    action,
    method,
    options,
    initial,
    submitLabel,
    cancelHref,
}: {
    action: string;
    method: 'post' | 'put';
    options: CampaignFormOptions;
    initial?: Partial<CampaignFormValues>;
    submitLabel: string;
    cancelHref: string;
}) {
    const [idempotencyKey] = useState<string>(() =>
        method === 'post' ? newIdempotencyKey() : '',
    );
    const form = useForm<CampaignFormValues>({
        ...defaults,
        idempotency_key: idempotencyKey,
        ...initial,
    });

    const requiresSms =
        form.data.channel === 'sms' || form.data.channel === 'multi_channel';
    const requiresVoice =
        form.data.channel === 'voice' ||
        form.data.channel === 'multi_channel';
    const requiresEmail = form.data.channel === 'email';
    const senderChannel =
        form.data.channel === 'multi_channel' ? 'sms' : form.data.channel;
    const senderOptions = options.senderIds.filter(
        (sender) => sender.channel === senderChannel,
    );
    const needsClient = options.clients.length > 0;

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        if (method === 'put') {
            form.put(action);

            return;
        }

        form.post(action);
    };

    return (
        <form onSubmit={submit} className="flex flex-col gap-4">
            <Card className="py-4">
                <CardHeader>
                    <CardTitle>Campaign details</CardTitle>
                    <CardDescription>
                        Name the campaign and choose how it reaches your
                        audience.
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-3 sm:grid-cols-2">
                    {needsClient && (
                        <div className="grid gap-1.5">
                            <Label htmlFor="client_id">Client account</Label>
                            <Select
                                value={form.data.client_id || 'none'}
                                onValueChange={(value) =>
                                    form.setData(
                                        'client_id',
                                        value === 'none' ? '' : value,
                                    )
                                }
                            >
                                <SelectTrigger id="client_id">
                                    <SelectValue placeholder="Select a client" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">
                                        Select a client
                                    </SelectItem>
                                    {options.clients.map((client) => (
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
                        <Label htmlFor="name">Campaign name</Label>
                        <Input
                            id="name"
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            placeholder="October arrears reminder"
                            required
                        />
                        <InputError message={form.errors.name} />
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor="channel">Channel</Label>
                        <Select
                            value={form.data.channel}
                            onValueChange={(value) =>
                                form.setData('channel', value)
                            }
                        >
                            <SelectTrigger id="channel">
                                <SelectValue placeholder="Select a channel" />
                            </SelectTrigger>
                            <SelectContent>
                                {options.channels.map((channel) => (
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
                        <Label htmlFor="contact_list_id">Audience</Label>
                        <Select
                            value={form.data.contact_list_id || 'all'}
                            onValueChange={(value) => {
                                form.setData(
                                    'contact_list_id',
                                    value === 'all' ? '' : value,
                                );
                                form.setData('contact_segment_id', '');
                            }}
                        >
                            <SelectTrigger id="contact_list_id">
                                <SelectValue placeholder="All contacts" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    All broadcastable contacts
                                </SelectItem>
                                {options.contactLists.map((list) => (
                                    <SelectItem
                                        key={list.id}
                                        value={String(list.id)}
                                    >
                                        {list.name} ({list.contacts_count})
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.contact_list_id} />
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor="contact_segment_id">Saved segment</Label>
                        <Select value={form.data.contact_segment_id || 'none'} onValueChange={(value) => { form.setData('contact_segment_id', value === 'none' ? '' : value); form.setData('contact_list_id', ''); }}>
                            <SelectTrigger id="contact_segment_id"><SelectValue placeholder="No saved segment" /></SelectTrigger>
                            <SelectContent><SelectItem value="none">No saved segment</SelectItem>{options.contactSegments.map((segment) => <SelectItem key={segment.id} value={String(segment.id)}>{segment.name} ({segment.contacts_count})</SelectItem>)}</SelectContent>
                        </Select>
                        <InputError message={form.errors.contact_segment_id} />
                        {options.contactLists.length === 0 &&
                            options.contactSegments.length === 0 && (
                                <p className="text-muted-foreground text-xs">
                                    No saved list or segment yet —{' '}
                                    <Link
                                        href={urls.contacts.audiences}
                                        className="underline underline-offset-2"
                                    >
                                        create an audience
                                    </Link>{' '}
                                    to narrow who this campaign reaches.
                                </p>
                            )}
                    </div>

                    {(requiresSms || requiresEmail) && (
                        <div className="grid gap-1.5">
                            <Label htmlFor="sender_id">
                                {requiresEmail ? 'From address' : 'Sender ID'}
                            </Label>
                            <Select
                                value={form.data.sender_id || 'none'}
                                onValueChange={(value) =>
                                    form.setData(
                                        'sender_id',
                                        value === 'none' ? '' : value,
                                    )
                                }
                            >
                                <SelectTrigger id="sender_id">
                                    <SelectValue placeholder="Default sender" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">
                                        Use the default sender
                                    </SelectItem>
                                    {senderOptions.map((sender) => (
                                        <SelectItem
                                            key={sender.id}
                                            value={String(sender.id)}
                                        >
                                            {sender.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {senderOptions.length === 0 && (
                                <p className="text-muted-foreground text-xs">
                                    No approved {senderChannel} sender ID yet —
                                    request one under Sender IDs.
                                </p>
                            )}
                            <InputError message={form.errors.sender_id} />
                        </div>
                    )}

                    {requiresVoice && (
                        <div className="grid gap-1.5">
                            <Label>Outbound caller ID</Label>
                            <div className="bg-muted/50 ring-border rounded-lg p-3 ring-1">
                                {options.outboundCallerId ? (
                                    <>
                                        <p className="font-mono text-xs">
                                            {options.outboundCallerId}
                                        </p>
                                        <p className="text-muted-foreground mt-1 text-xs">
                                            Every voice campaign dials from the
                                            platform DID. A reviewer approves
                                            the recording or script before
                                            delivery starts.
                                        </p>
                                    </>
                                ) : (
                                    <p className="text-destructive text-xs">
                                        No outbound DID is configured. An
                                        operator must set
                                        voice.outbound_caller_id before voice
                                        campaigns can run.
                                    </p>
                                )}
                            </div>
                        </div>
                    )}
                </CardContent>
            </Card>

            <Card className="py-4">
                <CardHeader>
                    <CardTitle>Message content</CardTitle>
                    <CardDescription>
                        Provide the copy that will be delivered over each
                        channel.
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-3">
                    {requiresSms && (
                        <div className="grid gap-1.5">
                            <Label htmlFor="sms_message">SMS message</Label>
                            <Textarea
                                id="sms_message"
                                value={form.data.sms_message}
                                onChange={(event) =>
                                    form.setData(
                                        'sms_message',
                                        event.target.value,
                                    )
                                }
                                rows={5}
                                maxLength={1600}
                                placeholder="Hello {{first_name}}, your account balance is due…"
                            />
                            <p className="text-muted-foreground text-xs">
                                {form.data.sms_message.length} of 1600
                                characters
                            </p>
                            <InputError message={form.errors.sms_message} />
                        </div>
                    )}

                    {requiresEmail && (
                        <div className="grid gap-3">
                            <div className="grid gap-1.5">
                                <Label htmlFor="email_subject">Subject</Label>
                                <Input
                                    id="email_subject"
                                    value={form.data.email_subject}
                                    onChange={(event) =>
                                        form.setData(
                                            'email_subject',
                                            event.target.value,
                                        )
                                    }
                                    maxLength={255}
                                    placeholder="Your October statement is ready"
                                />
                                <p className="text-muted-foreground text-xs">
                                    {form.data.email_subject.length} of 255
                                    characters
                                </p>
                                <InputError
                                    message={form.errors.email_subject}
                                />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="email_format">Format</Label>
                                <Select
                                    value={form.data.email_format}
                                    onValueChange={(value) =>
                                        form.setData('email_format', value)
                                    }
                                >
                                    <SelectTrigger id="email_format">
                                        <SelectValue placeholder="HTML" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="html">HTML</SelectItem>
                                        <SelectItem value="text">
                                            Plain text
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError
                                    message={form.errors.email_format}
                                />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="email_body">Body</Label>
                                <Textarea
                                    id="email_body"
                                    value={form.data.email_body}
                                    onChange={(event) =>
                                        form.setData(
                                            'email_body',
                                            event.target.value,
                                        )
                                    }
                                    rows={10}
                                    maxLength={100000}
                                    placeholder={
                                        form.data.email_format === 'html'
                                            ? '<p>Hello, your October statement is ready…</p>'
                                            : 'Hello, your October statement is ready…'
                                    }
                                />
                                <p className="text-muted-foreground text-xs">
                                    {form.data.email_format === 'html'
                                        ? 'Markup is rendered inside the branded broadcast shell, and a plain-text alternative is generated automatically.'
                                        : 'Sent exactly as typed, with no markup.'}
                                </p>
                                <InputError message={form.errors.email_body} />
                            </div>
                        </div>
                    )}

                    {requiresVoice && (
                        <div className="grid gap-3">
                            <div className="grid gap-1.5">
                                <Label htmlFor="voice_script">
                                    Voice script
                                </Label>
                                <Textarea
                                    id="voice_script"
                                    value={form.data.voice_script}
                                    onChange={(event) =>
                                        form.setData(
                                            'voice_script',
                                            event.target.value,
                                        )
                                    }
                                    rows={5}
                                    maxLength={4000}
                                    placeholder="Good afternoon, this is a reminder from…"
                                />
                                <InputError
                                    message={form.errors.voice_script}
                                />
                            </div>

                            <div className="grid gap-1.5 sm:max-w-xs">
                                <Label htmlFor="voice_max_duration_seconds">
                                    Maximum call duration (seconds)
                                </Label>
                                <Input
                                    id="voice_max_duration_seconds"
                                    type="number"
                                    min={5}
                                    max={300}
                                    value={
                                        form.data.voice_max_duration_seconds
                                    }
                                    onChange={(event) =>
                                        form.setData(
                                            'voice_max_duration_seconds',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={
                                        form.errors
                                            .voice_max_duration_seconds
                                    }
                                />
                            </div>

                            <div className="grid gap-3 sm:grid-cols-2">
                                <div className="grid gap-1.5">
                                    <Label htmlFor="ivr_enabled">
                                        Keypad menu
                                    </Label>
                                    <Select
                                        value={form.data.ivr_enabled || '0'}
                                        onValueChange={(value) =>
                                            form.setData('ivr_enabled', value)
                                        }
                                    >
                                        <SelectTrigger id="ivr_enabled">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="0">
                                                Play the message only
                                            </SelectItem>
                                            <SelectItem value="1">
                                                Offer a keypad menu
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        message={form.errors.ivr_enabled}
                                    />
                                </div>
                            </div>

                            {form.data.ivr_enabled === '1' && (
                                <div className="grid gap-3 rounded-lg border p-3">
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="ivr_prompt">
                                            Menu prompt
                                        </Label>
                                        <Textarea
                                            id="ivr_prompt"
                                            value={form.data.ivr_prompt}
                                            onChange={(event) =>
                                                form.setData(
                                                    'ivr_prompt',
                                                    event.target.value,
                                                )
                                            }
                                            rows={3}
                                            maxLength={1000}
                                            placeholder="Press 1 to hear the message, 2 to hear it again."
                                        />
                                        <p className="text-muted-foreground text-xs">
                                            Falls back to the voice script when
                                            left empty.
                                        </p>
                                        <InputError
                                            message={form.errors.ivr_prompt}
                                        />
                                    </div>

                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <div className="grid gap-1.5">
                                            <Label htmlFor="ivr_timeout_seconds">
                                                Wait for a key (seconds)
                                            </Label>
                                            <Input
                                                id="ivr_timeout_seconds"
                                                type="number"
                                                min={1}
                                                max={30}
                                                value={
                                                    form.data
                                                        .ivr_timeout_seconds
                                                }
                                                onChange={(event) =>
                                                    form.setData(
                                                        'ivr_timeout_seconds',
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                            <InputError
                                                message={
                                                    form.errors
                                                        .ivr_timeout_seconds
                                                }
                                            />
                                        </div>
                                        <div className="grid gap-1.5">
                                            <Label htmlFor="ivr_max_attempts">
                                                Prompt repeats
                                            </Label>
                                            <Input
                                                id="ivr_max_attempts"
                                                type="number"
                                                min={1}
                                                max={5}
                                                value={
                                                    form.data.ivr_max_attempts
                                                }
                                                onChange={(event) =>
                                                    form.setData(
                                                        'ivr_max_attempts',
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                            <InputError
                                                message={
                                                    form.errors
                                                        .ivr_max_attempts
                                                }
                                            />
                                        </div>
                                    </div>

                                    <div className="grid gap-2">
                                        <p className="text-sm font-medium">
                                            Key mapping
                                        </p>
                                        {ivrDigits.map((digit) => (
                                            <div
                                                key={digit}
                                                className="flex items-center gap-2"
                                            >
                                                <span className="bg-muted flex size-8 shrink-0 items-center justify-center rounded-md font-mono text-sm font-semibold">
                                                    {digit}
                                                </span>
                                                <Select
                                                    value={
                                                        form.data
                                                            .ivr_options[digit] ??
                                                        'none'
                                                    }
                                                    onValueChange={(value) => {
                                                        const next = {
                                                            ...form.data
                                                                .ivr_options,
                                                        };

                                                        if (value === 'none') {
                                                            delete next[digit];
                                                        } else {
                                                            next[digit] =
                                                                value;
                                                        }

                                                        form.setData(
                                                            'ivr_options',
                                                            next,
                                                        );
                                                    }}
                                                >
                                                    <SelectTrigger className="w-full max-w-xs">
                                                        <SelectValue placeholder="Not used" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="none">
                                                            Not used
                                                        </SelectItem>
                                                        {ivrActions.map(
                                                            (action) => (
                                                                <SelectItem
                                                                    key={
                                                                        action.value
                                                                    }
                                                                    value={
                                                                        action.value
                                                                    }
                                                                >
                                                                    {
                                                                        action.label
                                                                    }
                                                                </SelectItem>
                                                            ),
                                                        )}
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                        ))}
                                        <p className="text-muted-foreground text-xs">
                                            The opt-out key is always on the
                                            menu, so a recipient can leave from
                                            the handset.
                                        </p>
                                        <InputError
                                            message={form.errors.ivr_options}
                                        />
                                    </div>
                                </div>
                            )}
                        </div>
                    )}
                </CardContent>
            </Card>
            <Card className="py-4">
                <CardHeader>
                    <CardTitle>Delivery schedule</CardTitle>
                    <CardDescription>
                        Launch immediately or queue the campaign for a future
                        window.
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-3 sm:grid-cols-2">
                    <div className="grid gap-1.5">
                        <Label htmlFor="schedule_type">Schedule</Label>
                        <Select
                            value={form.data.schedule_type}
                            onValueChange={(value) =>
                                form.setData('schedule_type', value)
                            }
                        >
                            <SelectTrigger id="schedule_type">
                                <SelectValue placeholder="Immediate" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="now">
                                    Send immediately
                                </SelectItem>
                                <SelectItem value="scheduled">
                                    Schedule for later
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.schedule_type} />
                    </div>

                    {form.data.schedule_type === 'scheduled' && (
                        <div className="grid gap-1.5">
                            <Label htmlFor="scheduled_at">
                                Start time
                            </Label>
                            <Input
                                id="scheduled_at"
                                type="datetime-local"
                                value={form.data.scheduled_at}
                                onChange={(event) =>
                                    form.setData(
                                        'scheduled_at',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError message={form.errors.scheduled_at} />
                        </div>
                    )}

                    <div className="grid gap-1.5">
                        <Label htmlFor="timezone">Timezone</Label>
                        <Input
                            id="timezone"
                            value={form.data.timezone}
                            onChange={(event) =>
                                form.setData('timezone', event.target.value)
                            }
                            placeholder="Africa/Accra"
                        />
                        <InputError message={form.errors.timezone} />
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor="max_retries">Max retries</Label>
                        <Input
                            id="max_retries"
                            type="number"
                            min={1}
                            max={10}
                            value={form.data.max_retries}
                            onChange={(event) =>
                                form.setData('max_retries', event.target.value)
                            }
                        />
                        <InputError message={form.errors.max_retries} />
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor="retry_delay_minutes">
                            Retry delay (minutes)
                        </Label>
                        <Input
                            id="retry_delay_minutes"
                            type="number"
                            min={1}
                            max={1440}
                            value={form.data.retry_delay_minutes}
                            onChange={(event) =>
                                form.setData(
                                    'retry_delay_minutes',
                                    event.target.value,
                                )
                            }
                        />
                        <InputError
                            message={form.errors.retry_delay_minutes}
                        />
                    </div>

                    <div className="grid gap-1.5 sm:col-span-2">
                        <Label htmlFor="notes">Internal notes</Label>
                        <Textarea
                            id="notes"
                            value={form.data.notes}
                            onChange={(event) =>
                                form.setData('notes', event.target.value)
                            }
                            rows={3}
                            maxLength={2000}
                            placeholder="Context for reviewers and operators"
                        />
                        <InputError message={form.errors.notes} />
                    </div>
                </CardContent>
            </Card>

            <div className="flex items-center justify-end gap-2">
                <Button variant="outline" asChild>
                    <Link href={cancelHref}>Cancel</Link>
                </Button>
                <Button type="submit" disabled={form.processing}>
                    {form.processing ? 'Saving…' : submitLabel}
                </Button>
            </div>
        </form>
    );
}
