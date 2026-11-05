import { Head, router } from '@inertiajs/react';
import {
    BadgeDollarSign,
    CircleSlash,
    Clock3,
    CreditCard,
    RefreshCcw,
    Search,
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
    ClientOption,
    Option,
    Paginated,
    PaymentRow,
    PaymentStats,
} from '@/types';

type PageProps = {
    payments: Paginated<PaymentRow>;
    filters: {
        search: string;
        status: string;
        provider: string;
        client_id: string;
    };
    stats: PaymentStats;
    statuses: Option[];
    providers: Option[];
    clients: ClientOption[];
    can: { verify: boolean };
};

type FilterValues = {
    search: string;
    status: string;
    provider: string;
    client_id: string;
    page: number | null;
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

export default function AdminPaymentsIndex({
    payments,
    filters,
    stats,
    statuses,
    providers,
    clients,
    can,
}: PageProps) {
    const [search, setSearch] = useState(filters.search);
    const [verifying, setVerifying] = useState<number | null>(null);

    const apply = (changes: Partial<FilterValues>): void => {
        const next: FilterValues = {
            search: filters.search,
            status: filters.status,
            provider: filters.provider,
            client_id: filters.client_id,
            page: null,
            ...changes,
        };

        router.get(
            urls.admin.payments.index,
            {
                search: next.search === '' ? null : next.search,
                status: next.status === '' ? null : next.status,
                provider: next.provider === '' ? null : next.provider,
                client_id: next.client_id === '' ? null : next.client_id,
                page: next.page,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        apply({ search });
    };

    const verify = (payment: PaymentRow): void => {
        setVerifying(payment.id);

        router.post(
            urls.admin.payments.verify(payment.id),
            {},
            {
                preserveScroll: true,
                onFinish: () => setVerifying(null),
            },
        );
    };

    const currency = stats.currency;

    return (
        <>
            <Head title="Payments" />

            <div className="flex flex-col gap-4">
                <PageHeader
                    icon={CreditCard}
                    title="Payments"
                    description="Every wallet top up the gateway touched: what it collected, what is still open and why anything failed."
                />

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        title="Collected"
                        value={money(stats.collected, currency)}
                        description={`${stats.paid} settled payment${stats.paid === 1 ? '' : 's'}`}
                        icon={BadgeDollarSign}
                        tone="success"
                    />
                    <StatCard
                        title="Payments"
                        value={stats.total.toLocaleString()}
                        description="Attempts matching the filters"
                        icon={CreditCard}
                        tone="brand"
                    />
                    <StatCard
                        title="Open"
                        value={stats.pending.toLocaleString()}
                        description="Waiting on the card holder"
                        icon={Clock3}
                        tone="warning"
                    />
                    <StatCard
                        title="Failed"
                        value={stats.failed.toLocaleString()}
                        description="Declined or abandoned charges"
                        icon={CircleSlash}
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
                                    placeholder="Search reference or description"
                                    aria-label="Search payments"
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
                                    <SelectTrigger aria-label="Payment status">
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
                            <div className="grid min-w-40 gap-1.5">
                                <Select
                                    value={
                                        filters.provider === ''
                                            ? 'all'
                                            : filters.provider
                                    }
                                    onValueChange={(value) =>
                                        apply({
                                            provider:
                                                value === 'all' ? '' : value,
                                        })
                                    }
                                >
                                    <SelectTrigger aria-label="Payment provider">
                                        <SelectValue placeholder="Any gateway" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            Any gateway
                                        </SelectItem>
                                        {providers.map((provider) => (
                                            <SelectItem
                                                key={provider.value}
                                                value={provider.value}
                                            >
                                                {provider.label}
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
                        <CardTitle>Payment ledger</CardTitle>
                        <CardDescription>
                            Newest first. Re-check an open payment on the
                            gateway when a webhook never arrived.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="px-0">
                        {payments.data.length === 0 ? (
                            <EmptyState
                                icon={CreditCard}
                                title="No payments found"
                                description="Nothing matches the filters you chose. Clear the search and try again."
                            />
                        ) : (
                            <>
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="pl-6">
                                                Reference
                                            </TableHead>
                                            <TableHead>Client</TableHead>
                                            <TableHead>Package</TableHead>
                                            <TableHead>Amount</TableHead>
                                            <TableHead>Gateway</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead>Started</TableHead>
                                            <TableHead className="pr-6 text-right">
                                                Actions
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {payments.data.map((payment) => (
                                            <PaymentTableRow
                                                key={payment.id}
                                                payment={payment}
                                                canVerify={can.verify}
                                                verifying={
                                                    verifying === payment.id
                                                }
                                                onVerify={verify}
                                            />
                                        ))}
                                    </TableBody>
                                </Table>
                                <div className="px-6">
                                    <Pagination
                                        paginator={payments}
                                        noun="payments"
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

function PaymentTableRow({
    payment,
    canVerify,
    verifying,
    onVerify,
}: {
    payment: PaymentRow;
    canVerify: boolean;
    verifying: boolean;
    onVerify: (payment: PaymentRow) => void;
}) {
    return (
        <TableRow>
            <TableCell className="pl-6">
                <p className="font-mono text-xs">{payment.reference}</p>
                <p className="text-xs text-muted-foreground">
                    {payment.description}
                </p>
            </TableCell>
            <TableCell>{payment.client ?? '—'}</TableCell>
            <TableCell className="text-xs text-muted-foreground">
                {payment.package ?? '—'}
            </TableCell>
            <TableCell className="tabular-nums">
                {money(payment.amount, payment.currency)}
                {payment.is_settled &&
                    payment.amount_paid !== payment.amount && (
                        <p className="text-xs text-muted-foreground">
                            paid {money(payment.amount_paid, payment.currency)}
                        </p>
                    )}
            </TableCell>
            <TableCell className="text-xs capitalize">
                {payment.provider}
                {payment.channel !== null && (
                    <p className="text-muted-foreground">{payment.channel}</p>
                )}
            </TableCell>
            <TableCell>
                <StatusBadge status={payment.status} />
                {payment.failure_reason !== null && (
                    <p className="mt-1 max-w-48 text-xs text-muted-foreground">
                        {payment.failure_reason}
                    </p>
                )}
            </TableCell>
            <TableCell className="text-xs text-muted-foreground">
                {moment(payment.created_at)}
            </TableCell>
            <TableCell className="pr-6 text-right">
                {canVerify && payment.is_open ? (
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        disabled={verifying}
                        onClick={() => onVerify(payment)}
                    >
                        <RefreshCcw className="size-4" />
                        {verifying ? 'Checking…' : 'Verify'}
                    </Button>
                ) : (
                    <span className="text-xs text-muted-foreground">
                        {payment.invoice_number ?? '—'}
                    </span>
                )}
            </TableCell>
        </TableRow>
    );
}
