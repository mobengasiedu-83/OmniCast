import { Head, router } from '@inertiajs/react';
import { Ban, CircleDollarSign, ReceiptText, RotateCcw, Search } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import EmptyState from '@/components/empty-state';
import PageHeader from '@/components/page-header';
import Pagination from '@/components/pagination';
import StatCard from '@/components/stat-card';
import StatusBadge from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
import type {
    ClientOption,
    InvoiceRow,
    InvoiceStats,
    Option,
    Paginated,
} from '@/types';

type PageProps = {
    invoices: Paginated<InvoiceRow>;
    filters: {
        search: string;
        status: string;
        client_id: string;
    };
    stats: InvoiceStats;
    statuses: Option[];
    clients: ClientOption[];
};

function money(value: number, currency: string): string {
    return `${currency} ${value.toLocaleString('en-GH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

function moment(value: string | null): string {
    return value
        ? new Date(value).toLocaleString(undefined, {
              dateStyle: 'medium',
              timeStyle: 'short',
          })
        : '—';
}

export default function AdminInvoicesIndex({
    invoices,
    filters,
    stats,
    statuses,
    clients,
}: PageProps) {
    const [search, setSearch] = useState(filters.search);
    const [openInvoice, setOpenInvoice] = useState<InvoiceRow | null>(null);

    const apply = (changes: Partial<typeof filters>): void => {
        const next = { ...filters, ...changes };

        router.get(
            urls.admin.invoices.index,
            {
                search: next.search === '' ? null : next.search,
                status: next.status === '' ? null : next.status,
                client_id: next.client_id === '' ? null : next.client_id,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        apply({ search });
    };

    const currency = stats.currency;

    return (
        <>
            <Head title="Invoices" />

            <div className="flex flex-col gap-4">
                <PageHeader
                    icon={ReceiptText}
                    title="Invoices"
                    description="The tax invoices raised against settled wallet top ups, with the lines that built each total."
                />

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        title="Billed"
                        value={money(stats.billed, currency)}
                        description={`${stats.total} invoice${stats.total === 1 ? '' : 's'} issued`}
                        icon={CircleDollarSign}
                        tone="brand"
                    />
                    <StatCard
                        title="Paid"
                        value={stats.paid.toLocaleString()}
                        description="Settled and closed"
                        icon={ReceiptText}
                        tone="success"
                    />
                    <StatCard
                        title="Refunded"
                        value={stats.refunded.toLocaleString()}
                        description="Credit returned to the client"
                        icon={RotateCcw}
                        tone="warning"
                    />
                    <StatCard
                        title="Void"
                        value={stats.void.toLocaleString()}
                        description="Cancelled before settlement"
                        icon={Ban}
                        tone="danger"
                    />
                </div>

                <Card className="py-4">
                    <CardContent>
                        <form
                            onSubmit={submit}
                            className="flex flex-wrap items-end gap-3"
                        >
                            <div className="grid min-w-56 flex-1 gap-1.5">
                                <Input
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    placeholder="Search invoice number or payment reference"
                                    aria-label="Search invoices"
                                />
                            </div>
                            <div className="grid min-w-40 gap-1.5">
                                <Select
                                    value={
                                        filters.status === ''
                                            ? 'all'
                                            : filters.status
                                    }
                                    onValueChange={(value) =>
                                        apply({
                                            status:
                                                value === 'all' ? '' : value,
                                        })
                                    }
                                >
                                    <SelectTrigger aria-label="Invoice status">
                                        <SelectValue placeholder="Any status" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            Any status
                                        </SelectItem>
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
                                            client_id:
                                                value === 'all' ? '' : value,
                                        })
                                    }
                                >
                                    <SelectTrigger aria-label="Client account">
                                        <SelectValue placeholder="Every client" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            Every client
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
                            <Button type="submit" variant="outline">
                                <Search className="size-4" />
                                Search
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card className="overflow-hidden">
                    <CardHeader>
                        <CardTitle>Invoice register</CardTitle>
                        <CardDescription>
                            Newest first. Open an invoice to read the line items
                            it was built from.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="px-0">
                        {invoices.data.length === 0 ? (
                            <EmptyState
                                icon={ReceiptText}
                                title="No invoices found"
                                description="Nothing matches these filters yet. Invoices appear the moment a payment settles."
                            />
                        ) : (
                            <>
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="pl-6">
                                                Number
                                            </TableHead>
                                            <TableHead>Client</TableHead>
                                            <TableHead>Payment</TableHead>
                                            <TableHead>Total</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead>Issued</TableHead>
                                            <TableHead className="pr-6 text-right">
                                                Lines
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {invoices.data.map((invoice) => (
                                            <InvoiceTableRow
                                                key={invoice.id}
                                                invoice={invoice}
                                                onOpen={setOpenInvoice}
                                            />
                                        ))}
                                    </TableBody>
                                </Table>
                                <div className="px-6">
                                    <Pagination
                                        paginator={invoices}
                                        noun="invoices"
                                    />
                                </div>
                            </>
                        )}
                    </CardContent>
                </Card>

                <Dialog
                    open={openInvoice !== null}
                    onOpenChange={(open) => {
                        if (!open) {
                            setOpenInvoice(null);
                        }
                    }}
                >
                    <DialogContent className="sm:max-w-lg">
                        <DialogHeader>
                            <DialogTitle>
                                {openInvoice?.number ?? 'Invoice'}
                            </DialogTitle>
                            <DialogDescription>
                                {openInvoice?.client ?? 'Client'} ·{' '}
                                {moment(openInvoice?.issued_at ?? null)}
                            </DialogDescription>
                        </DialogHeader>

                        {openInvoice !== null && (
                            <div className="space-y-3">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Description</TableHead>
                                            <TableHead>Qty</TableHead>
                                            <TableHead>Unit</TableHead>
                                            <TableHead className="text-right">
                                                Amount
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {openInvoice.lines.map((line, index) => (
                                            <TableRow
                                                key={`${line.description}-${index}`}
                                            >
                                                <TableCell>
                                                    {line.description}
                                                </TableCell>
                                                <TableCell className="tabular-nums">
                                                    {line.quantity}
                                                </TableCell>
                                                <TableCell className="tabular-nums">
                                                    {money(
                                                        line.unit_price,
                                                        openInvoice.currency,
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {money(
                                                        line.amount,
                                                        openInvoice.currency,
                                                    )}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>

                                <dl className="space-y-1 text-sm">
                                    <div className="flex justify-between">
                                        <dt className="text-muted-foreground">
                                            Subtotal
                                        </dt>
                                        <dd className="tabular-nums">
                                            {money(
                                                openInvoice.subtotal,
                                                openInvoice.currency,
                                            )}
                                        </dd>
                                    </div>
                                    <div className="flex justify-between">
                                        <dt className="text-muted-foreground">
                                            Tax
                                        </dt>
                                        <dd className="tabular-nums">
                                            {money(
                                                openInvoice.tax,
                                                openInvoice.currency,
                                            )}
                                        </dd>
                                    </div>
                                    <div className="flex justify-between font-medium">
                                        <dt>Total</dt>
                                        <dd className="tabular-nums">
                                            {money(
                                                openInvoice.total,
                                                openInvoice.currency,
                                            )}
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                        )}
                    </DialogContent>
                </Dialog>
            </div>
        </>
    );
}

function InvoiceTableRow({
    invoice,
    onOpen,
}: {
    invoice: InvoiceRow;
    onOpen: (invoice: InvoiceRow) => void;
}) {
    return (
        <TableRow>
            <TableCell className="pl-6 font-mono text-xs">
                {invoice.number}
            </TableCell>
            <TableCell>{invoice.client ?? '—'}</TableCell>
            <TableCell className="text-muted-foreground font-mono text-xs">
                {invoice.payment_reference ?? '—'}
            </TableCell>
            <TableCell className="font-medium tabular-nums">
                {money(invoice.total, invoice.currency)}
            </TableCell>
            <TableCell>
                <StatusBadge status={invoice.status} />
            </TableCell>
            <TableCell className="text-muted-foreground text-xs">
                {moment(invoice.issued_at)}
            </TableCell>
            <TableCell className="pr-6 text-right">
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={() => onOpen(invoice)}
                >
                    {invoice.lines.length} line
                    {invoice.lines.length === 1 ? '' : 's'}
                </Button>
            </TableCell>
        </TableRow>
    );
}


