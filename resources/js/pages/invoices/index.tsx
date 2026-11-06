import { Head, router } from '@inertiajs/react';
import {
    CircleDollarSign,
    Download,
    ReceiptText,
    Search,
    Wallet,
} from 'lucide-react';
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
    Option,
    Paginated,
    PurchaseRow,
    PurchaseSummary,
} from '@/types';

type PageProps = {
    client: { id: number; name: string } | null;
    invoices: Paginated<PurchaseRow>;
    filters: { search: string; status: string };
    summary: PurchaseSummary;
    statuses: Option[];
    currency: string;
    can: { download: boolean };
};

function money(value: number, currency: string): string {
    return `${currency} ${value.toLocaleString('en-GH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

function moment(value: string | null): string {
    return value
        ? new Date(value).toLocaleDateString(undefined, {
              dateStyle: 'medium',
          })
        : '—';
}

/**
 * The client's purchase history: every invoice raised for the account, with a
 * PDF copy of each one.
 */
export default function InvoicesIndex({
    client,
    invoices,
    filters,
    summary,
    statuses,
    currency,
    can,
}: PageProps) {
    const [search, setSearch] = useState(filters.search);

    const apply = (changes: Partial<PageProps['filters']>): void => {
        const next = { ...filters, ...changes };

        router.get(
            urls.invoices.index,
            {
                search: next.search === '' ? null : next.search,
                status: next.status === '' ? null : next.status,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        apply({ search });
    };

    return (
        <>
            <Head title="Purchases" />

            <div className="flex flex-col gap-4">
                <PageHeader
                    icon={ReceiptText}
                    title="Purchases"
                    description={
                        client
                            ? `Every invoice raised for ${client.name}. Download a PDF copy for your records.`
                            : 'Every invoice raised for this account. Download a PDF copy for your records.'
                    }
                />

                <div className="grid gap-3 sm:grid-cols-3">
                    <StatCard
                        title="Spent"
                        value={money(summary.spent, summary.currency)}
                        description="Settled invoices and receipts"
                        icon={CircleDollarSign}
                        tone="brand"
                    />
                    <StatCard
                        title="Paid"
                        value={summary.paid.toLocaleString()}
                        description={`${summary.count} invoice${summary.count === 1 ? '' : 's'} issued`}
                        icon={Wallet}
                        tone="success"
                    />
                    <StatCard
                        title="Downloads"
                        value={summary.count.toLocaleString()}
                        description="A PDF is available for every invoice"
                        icon={Download}
                        tone="neutral"
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
                                    aria-label="Search purchases"
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
                                    <SelectTrigger aria-label="Filter by status">
                                        <SelectValue placeholder="Every status" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            Every status
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
                            <Button type="submit">
                                <Search className="size-4" />
                                Filter
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card className="overflow-hidden">
                    <CardHeader>
                        <CardTitle>Purchase history</CardTitle>
                        <CardDescription>
                            Newest first. Download the PDF for a VAT-ready copy
                            of any invoice.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="px-0">
                        {invoices.data.length === 0 ? (
                            <EmptyState
                                icon={ReceiptText}
                                title="No purchases yet"
                                description="Invoices appear here the moment a wallet top up settles."
                                action={
                                    <Button variant="outline" asChild>
                                        <a href={urls.wallet.index}>
                                            <Wallet className="size-4" />
                                            Fund the wallet
                                        </a>
                                    </Button>
                                }
                            />
                        ) : (
                            <>
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="pl-6">
                                                Invoice
                                            </TableHead>
                                            <TableHead>Paid for</TableHead>
                                            <TableHead>Payment</TableHead>
                                            <TableHead>Total</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead>Issued</TableHead>
                                            <TableHead className="pr-6 text-right">
                                                PDF
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {invoices.data.map((invoice) => (
                                            <PurchaseTableRow
                                                key={invoice.id}
                                                invoice={invoice}
                                                canDownload={can.download}
                                            />
                                        ))}
                                    </TableBody>
                                </Table>
                                <div className="px-6">
                                    <Pagination
                                        paginator={invoices}
                                        noun="purchases"
                                    />
                                </div>
                            </>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

/**
 * One invoice in the purchase history, with its PDF download.
 */
function PurchaseTableRow({
    invoice,
    canDownload,
}: {
    invoice: PurchaseRow;
    canDownload: boolean;
}) {
    return (
        <TableRow>
            <TableCell className="pl-6 font-medium">{invoice.number}</TableCell>
            <TableCell className="text-muted-foreground">
                {invoice.description}
            </TableCell>
            <TableCell className="text-muted-foreground">
                <span className="capitalize">
                    {invoice.channel ?? 'Card'}
                </span>
                {invoice.payment_reference && (
                    <span className="block font-mono text-[11px]">
                        {invoice.payment_reference}
                    </span>
                )}
            </TableCell>
            <TableCell>{money(invoice.total, invoice.currency)}</TableCell>
            <TableCell>
                <StatusBadge
                    status={invoice.status}
                    label={invoice.status_label}
                />
            </TableCell>
            <TableCell className="text-muted-foreground">
                {moment(invoice.issued_at)}
            </TableCell>
            <TableCell className="pr-6 text-right">
                {canDownload ? (
                    <Button variant="ghost" size="sm" asChild>
                        <a href={invoice.download_url}>
                            <Download className="size-4" />
                            PDF
                        </a>
                    </Button>
                ) : (
                    <span className="text-xs text-muted-foreground">
                        No access
                    </span>
                )}
            </TableCell>
        </TableRow>
    );
}

