import { Link, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
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
import type { CampaignFormOptions, CampaignFormValues } from '@/types';

const defaults: CampaignFormValues = {
    name: '',
    channel: 'sms',
    contact_list_id: '',
    sender_id: '',
    caller_id: '',
    sms_message: '',
    voice_script: '',
    voice_max_duration_seconds: '30',
    schedule_type: 'now',
    scheduled_at: '',
    timezone: 'Africa/Accra',
    max_retries: '3',
    retry_delay_minutes: '15',
    notes: '',
    client_id: '',
};

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
    const form = useForm<CampaignFormValues>({ ...defaults, ...initial });

    const requiresSms = form.data.channel !== 'voice';
    const requiresVoice = form.data.channel !== 'sms';
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
                            onValueChange={(value) =>
                                form.setData(
                                    'contact_list_id',
                                    value === 'all' ? '' : value,
                                )
                            }
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

                    {requiresSms && (
                        <div className="grid gap-1.5">
                            <Label htmlFor="sender_id">Sender ID</Label>
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
                                    {options.senderIds.map((sender) => (
                                        <SelectItem
                                            key={sender.id}
                                            value={String(sender.id)}
                                        >
                                            {sender.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.sender_id} />
                        </div>
                    )}

                    {requiresVoice && (
                        <div className="grid gap-1.5">
                            <Label htmlFor="caller_id">Caller ID</Label>
                            <Select
                                value={form.data.caller_id || 'none'}
                                onValueChange={(value) =>
                                    form.setData(
                                        'caller_id',
                                        value === 'none' ? '' : value,
                                    )
                                }
                            >
                                <SelectTrigger id="caller_id">
                                    <SelectValue placeholder="Default caller" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">
                                        Use the default caller
                                    </SelectItem>
                                    {options.callerIds.map((caller) => (
                                        <SelectItem
                                            key={caller.id}
                                            value={String(caller.id)}
                                        >
                                            {caller.label ?? caller.number}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.caller_id} />
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
