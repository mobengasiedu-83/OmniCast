import { Link, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { ClientOption, SenderIdChannelOption, SenderIdFormValues } from '@/types';

const defaults: SenderIdFormValues = {
    channel: 'sms',
    name: '',
    country: 'GH',
    purpose: '',
    client_id: '',
};

export default function SenderIdForm({
    action,
    method,
    channels,
    clients,
    initial,
    submitLabel,
    cancelHref,
}: {
    action: string;
    method: 'post' | 'put';
    channels: SenderIdChannelOption[];
    clients: ClientOption[];
    initial?: Partial<SenderIdFormValues>;
    submitLabel: string;
    cancelHref: string;
}) {
    const form = useForm<SenderIdFormValues>({ ...defaults, ...initial });

    const channel = channels.find((option) => option.value === form.data.channel) ?? channels[0];

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        if (method === 'put') {
            form.put(action);

            return;
        }

        form.post(action);
    };

    const changeChannel = (value: string): void => {
        const next = channels.find((option) => option.value === value);

        form.setData((data) => ({
            ...data,
            channel: value,
            country: next?.requires_country ? data.country || 'GH' : '',
        }));
    };

    return (
        <form onSubmit={submit} className="flex flex-col gap-4">
            <Card className="py-4">
                <CardHeader>
                    <CardTitle>Sender identity</CardTitle>
                    <CardDescription>
                        Request a sender ID for SMS, an email from address, or a voice caller number.
                        Compliance reviews every request before it can be used in a campaign.
                    </CardDescription>
                </CardHeader>

                <CardContent className="grid gap-3 sm:grid-cols-2">
                    {clients.length > 0 && (
                        <div className="grid gap-1.5 sm:col-span-2">
                            <Label htmlFor="client_id">Client account</Label>
                            <Select
                                value={form.data.client_id || 'none'}
                                onValueChange={(value) => form.setData('client_id', value === 'none' ? '' : value)}
                            >
                                <SelectTrigger id="client_id">
                                    <SelectValue placeholder="Select a client" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">Select a client</SelectItem>
                                    {clients.map((client) => (
                                        <SelectItem key={client.id} value={String(client.id)}>
                                            {client.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.client_id} />
                        </div>
                    )}

                    <div className="grid gap-1.5 sm:col-span-2">
                        <Label htmlFor="channel">Channel</Label>
                        <Select value={form.data.channel} onValueChange={changeChannel}>
                            <SelectTrigger id="channel">
                                <SelectValue placeholder="Select a channel" />
                            </SelectTrigger>
                            <SelectContent>
                                {channels.map((option) => (
                                    <SelectItem key={option.value} value={option.value}>
                                        {option.label} — {option.description}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.channel} />
                    </div>

                    <div className="grid gap-1.5">
                        <div className="flex items-center justify-between gap-2">
                            <Label htmlFor="name">{channel?.identifier_label ?? 'Sender ID'}</Label>
                            {channel && (
                                <span className="text-muted-foreground text-xs tabular-nums">
                                    {form.data.name.length}/{channel.max_length}
                                </span>
                            )}
                        </div>
                        <Input
                            id="name"
                            value={form.data.name}
                            maxLength={channel?.max_length}
                            onChange={(event) => form.setData('name', event.target.value)}
                            placeholder={channel?.placeholder}
                            autoComplete="off"
                        />
                        {channel && <p className="text-muted-foreground text-xs">{channel.hint}</p>}
                        <InputError message={form.errors.name} />
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor="country">Country</Label>
                        <Input
                            id="country"
                            value={form.data.country}
                            maxLength={2}
                            disabled={channel ? !channel.requires_country : false}
                            onChange={(event) => form.setData('country', event.target.value.toUpperCase())}
                            placeholder="GH"
                        />
                        <p className="text-muted-foreground text-xs">
                            Two-letter ISO code
                            {channel && !channel.requires_country
                                ? ' (not required for email senders).'
                                : ' the sender ID is registered in.'}
                        </p>
                        <InputError message={form.errors.country} />
                    </div>

                    <div className="grid gap-1.5 sm:col-span-2">
                        <Label htmlFor="purpose">Intended use</Label>
                        <Textarea
                            id="purpose"
                            rows={3}
                            value={form.data.purpose}
                            onChange={(event) => form.setData('purpose', event.target.value)}
                            placeholder="Explain the messages this sender ID will be used for."
                        />
                        <InputError message={form.errors.purpose} />
                    </div>
                </CardContent>
            </Card>

            <div className="flex items-center justify-end gap-2">
                <Button variant="outline" asChild>
                    <Link href={cancelHref}>Cancel</Link>
                </Button>
                <Button type="submit" disabled={form.processing}>
                    {form.processing ? 'Submitting…' : submitLabel}
                </Button>
            </div>
        </form>
    );
}
