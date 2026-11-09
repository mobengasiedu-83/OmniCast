import { Link } from '@inertiajs/react';
import StatusBadge from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { Paginated, SupportTicketRow } from '@/types';

/**
 * The support register, as the enquiry and ticket screens print it.
 *
 * The queue order is computed by the server (urgent first, then newest), so the
 * table never re-sorts: the row at the top is the row to answer next.
 */
export default function SupportTicketTable({
    rows,
    showClient = false,
    showAssignee = false,
    emptyMessage,
}: {
    rows: Paginated<SupportTicketRow>;
    showClient?: boolean;
    showAssignee?: boolean;
    emptyMessage: string;
}) {
    return (
        <>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Conversation</TableHead>
                        <TableHead>Requester</TableHead>
                        {showClient && <TableHead>Organisation</TableHead>}
                        <TableHead>Priority</TableHead>
                        <TableHead>Status</TableHead>
                        {showAssignee && <TableHead>Owner</TableHead>}
                        <TableHead>Waiting</TableHead>
                    </TableRow>
                </TableHeader>

                <TableBody>
                    {rows.data.map((row) => (
                        <TableRow key={row.id}>
                            <TableCell className="max-w-[22rem]">
                                <Link
                                    href={row.url}
                                    className="font-medium hover:text-brand"
                                >
                                    {row.subject}
                                </Link>
                                <p className="text-xs text-muted-foreground">
                                    {row.reference}
                                    {row.category ? ` · ${row.category}` : ''}
                                    {row.team ? ` · ${row.team}` : ''}
                                </p>
                            </TableCell>

                            <TableCell>
                                {row.requester}
                                <p className="text-xs text-muted-foreground">
                                    {row.email}
                                </p>
                            </TableCell>

                            {showClient && (
                                <TableCell>{row.client ?? '—'}</TableCell>
                            )}

                            <TableCell>
                                <Badge
                                    variant={
                                        row.priority === 'urgent'
                                            ? 'destructive'
                                            : row.priority === 'high'
                                              ? 'warning'
                                              : 'muted'
                                    }
                                >
                                    {row.priority_label}
                                </Badge>
                            </TableCell>

                            <TableCell>
                                <StatusBadge
                                    status={row.status}
                                    label={row.status_label}
                                />
                            </TableCell>

                            {showAssignee && (
                                <TableCell>
                                    {row.assignee ?? 'Unassigned'}
                                </TableCell>
                            )}

                            <TableCell className="text-xs whitespace-nowrap text-muted-foreground">
                                {row.waiting_since}
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>

            {rows.data.length === 0 && (
                <p className="p-8 text-center text-sm text-muted-foreground">
                    {emptyMessage}
                </p>
            )}
        </>
    );
}
