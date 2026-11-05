import { Head, router } from '@inertiajs/react';
import { Save, Tags, TrendingUp } from 'lucide-react';
import { useEffect, useState } from 'react';
import EmptyState from '@/components/empty-state';
import PageHeader from '@/components/page-header';
import StatCard from '@/components/stat-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { urls } from '@/lib/urls';
import type { ClientOption, Option, RateCardRow, RateRow } from '@/types';

type PageProps = {
    rateCards: RateCardRow[];
    filters: {
        channel: string;
        client_id: string;
    };
    channels: Option[];
    clients: ClientOption[];
    stats: {
        cards: number;
        rates: number;
    };
    can: {
        manage: boolean;
    };
};

function money(value: number, currency: string): string {
    return `${currency} ${value.toLocaleString('en-GH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 4,
    })}`;
}

export default function AdminRateCardsIndex({
    rateCards,
    filters,
    channels,
    clients,
    stats,
    can,
}: PageProps) {
    const [saving, setSaving] = useState<number | null>(null);

    const apply = (changes: Partial<typeof filters>): void => {
        const next = { ...filters, ...changes };

        router.get(
            urls.admin.rateCards.index,
            {
                channel: next.channel === '' ? null : next.channel,
                client_id: next.client_id === '' ? null : next.client_id,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const saveRate =
        (rate: RateRow) =>
        (values: { unit_price: string; supplier_cost: string }): void => {
            setSaving(rate.id);

            router.put(urls.admin.rateCards.updateRate(rate.id), values, {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => setSaving(null),
            });
        };

    return (
        <>
            <Head title="Rate cards" />

            <div className="flex flex-col gap-4">
                <PageHeader
                    icon={Tags}
                    title="Rate cards"
                    description="What each destination bills at. Client cards override the platform default, so check the card before changing a price."
                />

                <div className="grid gap-3 sm:grid-cols-2">
                    <StatCard
                        title="Rate cards"
                        value={stats.cards.toLocaleString()}
                        description="Matching the filters below"
                        icon={Tags}
                        tone="brand"
                    />
                    <StatCard
                        title="Priced destinations"
                        value={stats.rates.toLocaleString()}
                        description="Across every card in view"
                        icon={TrendingUp}
                        tone="signal"
                    />
                </div>

                <Card className="py-4">
                    <CardContent className="flex flex-wrap items-end gap-3">
                        <div className="grid min-w-40 gap-1.5">
                            <Select
                                value={
                                    filters.channel === ''
                                        ? 'all'
                                        : filters.channel
                                }
                                onValueChange={(value) =>
                                    apply({
                                        channel: value === 'all' ? '' : value,
                                    })
                                }
                            >
                                <SelectTrigger aria-label="Channel">
                                    <SelectValue placeholder="Every channel" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        Every channel
                                    </SelectItem>
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
                        <div className="grid min-w-48 gap-1.5">
                            <Select
                                value={
                                    filters.client_id === ''
                                        ? 'all'
                                        : filters.client_id
                                }
                                onValueChange={(value) =>
                                    apply({
                                        client_id: value === 'all' ? '' : value,
                                    })
                                }
                            >
                                <SelectTrigger aria-label="Rate card owner">
                                    <SelectValue placeholder="Every card" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        Every card
                                    </SelectItem>
                                    <SelectItem value="platform">
                                        Platform default
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
                        </div>
                    </CardContent>
                </Card>

                {rateCards.length === 0 ? (
                    <Card>
                        <CardContent>
                            <EmptyState
                                icon={Tags}
                                title="No rate cards found"
                                description="Nothing matches these filters. Clear them to see the platform default card."
                            />
                        </CardContent>
                    </Card>
                ) : (
                    rateCards.map((card) => (
                        <RateCardPanel
                            key={card.id}
                            card={card}
                            canManage={can.manage}
                            saving={saving}
                            onSave={saveRate}
                        />
                    ))
                )}
            </div>
        </>
    );
}

function RateCardPanel({
    card,
    canManage,
    saving,
    onSave,
}: {
    card: RateCardRow;
    canManage: boolean;
    saving: number | null;
    onSave: (
        rate: RateRow,
    ) => (values: { unit_price: string; supplier_cost: string }) => void;
}) {
    return (
        <Card className="overflow-hidden">
            <CardHeader>
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="space-y-1">
                        <CardTitle className="flex flex-wrap items-center gap-2">
                            {card.name}
                            <Badge variant="outline">
                                {card.channel_label}
                            </Badge>
                            {card.is_default && (
                                <Badge variant="secondary">Default</Badge>
                            )}
                        </CardTitle>
                        <CardDescription>
                            {card.client} · {card.currency}
                            {card.effective_from !== null &&
                                ` · from ${card.effective_from}`}
                            {card.effective_to !== null &&
                                ` · to ${card.effective_to}`}
                        </CardDescription>
                    </div>

                    <p className="text-xs text-muted-foreground">
                        {card.rates.length} destination
                        {card.rates.length === 1 ? '' : 's'}
                    </p>
                </div>
            </CardHeader>
            <CardContent className="px-0">
                {card.rates.length === 0 ? (
                    <EmptyState
                        icon={Tags}
                        title="No prices on this card"
                        description="Add destinations to this card before campaigns can be billed against it."
                    />
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="pl-6">
                                    Destination
                                </TableHead>
                                <TableHead>Bills per</TableHead>
                                <TableHead>Unit price</TableHead>
                                <TableHead>Supplier cost</TableHead>
                                <TableHead>Margin</TableHead>
                                {canManage && (
                                    <TableHead className="pr-6 text-right">
                                        Save
                                    </TableHead>
                                )}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {card.rates.map((rate) => (
                                <RateEditorRow
                                    key={rate.id}
                                    rate={rate}
                                    currency={card.currency}
                                    canManage={canManage}
                                    saving={saving === rate.id}
                                    onSave={onSave(rate)}
                                />
                            ))}
                        </TableBody>
                    </Table>
                )}
            </CardContent>
        </Card>
    );
}

function RateEditorRow({
    rate,
    currency,
    canManage,
    saving,
    onSave,
}: {
    rate: RateRow;
    currency: string;
    canManage: boolean;
    saving: boolean;
    onSave: (values: { unit_price: string; supplier_cost: string }) => void;
}) {
    const [unitPrice, setUnitPrice] = useState(String(rate.unit_price));
    const [supplierCost, setSupplierCost] = useState(
        String(rate.supplier_cost),
    );

    useEffect(() => {
        setUnitPrice(String(rate.unit_price));
        setSupplierCost(String(rate.supplier_cost));
    }, [rate.unit_price, rate.supplier_cost]);

    const dirty =
        unitPrice !== String(rate.unit_price) ||
        supplierCost !== String(rate.supplier_cost);

    return (
        <TableRow>
            <TableCell className="pl-6">
                <p className="font-medium">{rate.destination_label}</p>
                <p className="font-mono text-xs text-muted-foreground">
                    {rate.destination}
                </p>
            </TableCell>
            <TableCell className="text-xs text-muted-foreground">
                {rate.billing_unit_label}
            </TableCell>
            <TableCell>
                {canManage ? (
                    <div className="grid gap-1">
                        <Input
                            type="number"
                            min={0}
                            step="0.0001"
                            value={unitPrice}
                            onChange={(event) =>
                                setUnitPrice(event.target.value)
                            }
                            aria-label={`Unit price for ${rate.destination_label}`}
                            className="w-28 tabular-nums"
                        />
                        <span className="text-[11px] text-muted-foreground">
                            {money(Number(unitPrice || 0), currency)}
                        </span>
                    </div>
                ) : (
                    <span className="tabular-nums">
                        {money(rate.unit_price, currency)}
                    </span>
                )}
            </TableCell>
            <TableCell>
                {canManage ? (
                    <Input
                        type="number"
                        min={0}
                        step="0.0001"
                        value={supplierCost}
                        onChange={(event) =>
                            setSupplierCost(event.target.value)
                        }
                        aria-label={`Supplier cost for ${rate.destination_label}`}
                        className="w-28 tabular-nums"
                    />
                ) : (
                    <span className="text-muted-foreground tabular-nums">
                        {money(rate.supplier_cost, currency)}
                    </span>
                )}
            </TableCell>
            <TableCell className="tabular-nums">
                {money(rate.margin, currency)}
            </TableCell>
            {canManage && (
                <TableCell className="pr-6 text-right">
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        disabled={saving || !dirty}
                        onClick={() =>
                            onSave({
                                unit_price: unitPrice,
                                supplier_cost: supplierCost,
                            })
                        }
                    >
                        <Save className="size-4" />
                        {saving ? 'Saving…' : 'Save'}
                    </Button>
                </TableCell>
            )}
        </TableRow>
    );
}
