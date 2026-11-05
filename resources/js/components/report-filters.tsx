import { router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { ReportCampaignOption, ReportOption } from '@/types';

export type ReportFilterValues = {
    from: string;
    to: string;
    channel?: string;
    campaign?: number | null;
    client_id?: number | null;
};

/**
 * The date/channel/campaign/client filter bar every report screen uses.
 *
 * The component owns the draft dates and pushes the rest straight into the
 * query string, so a screen never has to re-implement filter plumbing.
 */
export default function ReportFilters({
    url,
    values,
    channels,
    campaigns,
    clients,
}: {
    url: string;
    values: ReportFilterValues;
    channels?: ReportOption[];
    campaigns?: ReportCampaignOption[];
    clients?: Array<{ id: number; name: string }>;
}) {
    const [from, setFrom] = useState(values.from);
    const [to, setTo] = useState(values.to);

    const apply = (overrides: Record<string, string | number | null>): void => {
        router.get(
            url,
            {
                from: values.from,
                to: values.to,
                channel: values.channel ?? '',
                campaign: values.campaign ?? null,
                client_id: values.client_id ?? null,
                ...overrides,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        apply({ from: from || null, to: to || null });
    };

    return (
        <form onSubmit={submit} className="flex flex-wrap items-end gap-3">
            <div className="grid gap-1.5">
                <Label htmlFor="report-from">From</Label>
                <Input
                    id="report-from"
                    type="date"
                    value={from}
                    onChange={(event) => setFrom(event.target.value)}
                />
            </div>

            <div className="grid gap-1.5">
                <Label htmlFor="report-to">To</Label>
                <Input
                    id="report-to"
                    type="date"
                    value={to}
                    onChange={(event) => setTo(event.target.value)}
                />
            </div>

            {channels && (
                <div className="grid gap-1.5">
                    <Label htmlFor="report-channel">Channel</Label>
                    <Select
                        value={values.channel || 'all'}
                        onValueChange={(value) =>
                            apply({ channel: value === 'all' ? null : value })
                        }
                    >
                        <SelectTrigger id="report-channel" className="w-44">
                            <SelectValue placeholder="Every channel" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Every channel</SelectItem>
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
                </div>
            )}

            {campaigns && (
                <div className="grid gap-1.5">
                    <Label htmlFor="report-campaign">Campaign</Label>
                    <Select
                        value={
                            values.campaign ? String(values.campaign) : 'all'
                        }
                        onValueChange={(value) =>
                            apply({
                                campaign:
                                    value === 'all' ? null : Number(value),
                            })
                        }
                    >
                        <SelectTrigger id="report-campaign" className="w-56">
                            <SelectValue placeholder="Every campaign" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Every campaign</SelectItem>
                            {campaigns.map((campaign) => (
                                <SelectItem
                                    key={campaign.id}
                                    value={String(campaign.id)}
                                >
                                    {campaign.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
            )}

            {clients && clients.length > 0 && (
                <div className="grid gap-1.5">
                    <Label htmlFor="report-client">Client</Label>
                    <Select
                        value={
                            values.client_id ? String(values.client_id) : 'all'
                        }
                        onValueChange={(value) =>
                            apply({
                                client_id:
                                    value === 'all' ? null : Number(value),
                            })
                        }
                    >
                        <SelectTrigger id="report-client" className="w-48">
                            <SelectValue placeholder="Every client" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Every client</SelectItem>
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
                </div>
            )}

            <Button type="submit" variant="secondary">
                Apply
            </Button>
        </form>
    );
}
