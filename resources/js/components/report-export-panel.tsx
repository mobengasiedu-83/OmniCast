import { router } from '@inertiajs/react';
import { Download, FileSpreadsheet } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import EmptyState from '@/components/empty-state';
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
import { Label } from '@/components/ui/label';
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
import type { ClientOption, ReportExportRow, ReportOption } from '@/types';

/**
 * Bytes in the unit a person reads fastest.
 */
export function fileSize(bytes: number | null): string {
    if (bytes === null || bytes <= 0) {
        return '—';
    }

    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export function moment(value: string | null): string {
    return value
        ? new Date(value).toLocaleString(undefined, {
              dateStyle: 'medium',
              timeStyle: 'short',
          })
        : '—';
}

/**
 * The report file workflow: ask for a CSV, then watch the row move from
 * `pending` to `completed` and download it.
 *
 * Both the tenant screen and the platform screen render this component; they
 * only differ in the tenant column and the download route they build.
 */
export default function ReportExportPanel({
    action,
    exports,
    reports,
    defaults,
    clients,
    defaultReport,
    canExport = true,
    download,
}: {
    action: string;
    exports: ReportExportRow[];
    reports: ReportOption[];
    defaults: { from: string; to: string };
    clients?: ClientOption[];
    defaultReport?: string;
    canExport?: boolean;
    download: (row: ReportExportRow) => string;
}) {
    const [report, setReport] = useState(
        defaultReport && reports.some((option) => option.value === defaultReport)
            ? defaultReport
            : (reports[0]?.value ?? ''),
    );
    const [from, setFrom] = useState(defaults.from);
    const [to, setTo] = useState(defaults.to);
    const [clientId, setClientId] = useState('all');

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        router.post(
            action,
            {
                report,
                from: from || null,
                to: to || null,
                client_id: clientId === 'all' ? null : Number(clientId),
            },
            { preserveScroll: true },
        );
    };

    return (
        <div className="flex flex-col gap-4">
            {canExport && (
                <Card>
                    <CardHeader>
                        <CardTitle>Generate an export</CardTitle>
                        <CardDescription>
                            The file is written in the background and appears in
                            the list below when it is ready.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={submit}
                            className="flex flex-wrap items-end gap-3"
                        >
                            <div className="grid gap-1.5">
                                <Label htmlFor="export-report">Report</Label>
                                <Select
                                    value={report}
                                    onValueChange={setReport}
                                >
                                    <SelectTrigger
                                        id="export-report"
                                        className="w-60"
                                    >
                                        <SelectValue placeholder="Pick a report" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {reports.map((option) => (
                                            <SelectItem
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="export-from">From</Label>
                                <Input
                                    id="export-from"
                                    type="date"
                                    value={from}
                                    onChange={(event) =>
                                        setFrom(event.target.value)
                                    }
                                />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="export-to">To</Label>
                                <Input
                                    id="export-to"
                                    type="date"
                                    value={to}
                                    onChange={(event) =>
                                        setTo(event.target.value)
                                    }
                                />
                            </div>

                            {clients && clients.length > 0 && (
                                <div className="grid gap-1.5">
                                    <Label htmlFor="export-client">Client</Label>
                                    <Select
                                        value={clientId}
                                        onValueChange={setClientId}
                                    >
                                        <SelectTrigger
                                            id="export-client"
                                            className="w-48"
                                        >
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
                            )}

                            <Button type="submit">Generate export</Button>
                        </form>
                    </CardContent>
                </Card>
            )}

            <Card>
                <CardHeader>
                    <CardTitle>Export history</CardTitle>
                    <CardDescription>
                        Every file this account generated, including the ones
                        the scheduler produced.
                    </CardDescription>
                </CardHeader>
                <CardContent className="px-0">
                    {exports.length === 0 ? (
                        <EmptyState
                            icon={FileSpreadsheet}
                            title="No exports yet"
                            description="Generate a report above and it will be listed here with its row count and size."
                        />
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="pl-6">
                                        Report
                                    </TableHead>
                                    {clients && <TableHead>Client</TableHead>}
                                    <TableHead>Period</TableHead>
                                    <TableHead>Source</TableHead>
                                    <TableHead className="text-right">
                                        Rows
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Size
                                    </TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Created</TableHead>
                                    <TableHead className="pr-6 text-right">
                                        File
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {exports.map((row) => (
                                    <TableRow key={row.id}>
                                        <TableCell className="pl-6 font-medium">
                                            {row.report_label}
                                        </TableCell>
                                        {clients && (
                                            <TableCell className="text-muted-foreground">
                                                {row.client ?? 'Platform'}
                                            </TableCell>
                                        )}
                                        <TableCell className="text-muted-foreground whitespace-nowrap">
                                            {row.period}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground capitalize">
                                            {row.source}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {row.rows?.toLocaleString() ?? '—'}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {fileSize(row.size)}
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge
                                                status={row.status}
                                                label={row.status_label}
                                            />
                                        </TableCell>
                                        <TableCell className="text-muted-foreground whitespace-nowrap">
                                            {moment(row.created_at)}
                                        </TableCell>
                                        <TableCell className="pr-6 text-right">
                                            {row.downloadable ? (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <a href={download(row)}>
                                                        <Download />
                                                        CSV
                                                    </a>
                                                </Button>
                                            ) : (
                                                <span className="text-muted-foreground text-xs">
                                                    {row.expired
                                                        ? 'Pruned'
                                                        : row.error_message
                                                          ? 'Failed'
                                                          : 'Working'}
                                                </span>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}

