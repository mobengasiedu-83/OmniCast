import { Head, useForm } from '@inertiajs/react';
import { Pencil, Trash2, UsersRound } from 'lucide-react';
import { useState } from 'react';
import { useConfirm } from '@/components/confirm-dialog';
import PageHeader from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import teams from '@/routes/admin/support/teams';

type TeamRow = {
    id: number;
    name: string;
    slug: string;
    email: string | null;
    description: string | null;
    is_active: boolean;
    sort_order: number;
    categories_count: number;
    tickets_count: number;
};

type PageProps = { teams: TeamRow[] };

type TeamForm = {
    name: string;
    email: string;
    description: string;
    is_active: boolean;
    sort_order: string;
};

const BLANK: TeamForm = {
    name: '',
    email: '',
    description: '',
    is_active: true,
    sort_order: '0',
};

/**
 * The teams conversations are routed to.
 *
 * A team is an inbox and a name rather than a group of accounts: the address is who
 * gets told when a conversation lands, and the categories decide which ones land
 * there.
 */
export default function SupportTeams({ teams: rows }: PageProps) {
    const confirm = useConfirm();
    const [editing, setEditing] = useState<TeamRow | null>(null);
    const form = useForm<TeamForm>({ ...BLANK });

    const startCreate = (): void => {
        setEditing(null);
        form.setData({ ...BLANK });
        form.clearErrors();
    };

    const startEdit = (row: TeamRow): void => {
        setEditing(row);
        form.setData({
            name: row.name,
            email: row.email ?? '',
            description: row.description ?? '',
            is_active: row.is_active,
            sort_order: String(row.sort_order),
        });
        form.clearErrors();
    };

    const submit = (): void => {
        if (editing !== null) {
            form.put(teams.update.url(editing.id), {
                preserveScroll: true,
                onSuccess: () => setEditing(null),
            });

            return;
        }

        form.post(teams.store.url(), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    const remove = async (row: TeamRow): Promise<void> => {
        if (
            !(await confirm({
                title: `Delete the ${row.name} team?`,
                description:
                    'A team that still owns categories or conversations cannot be deleted — move them first.',
                confirmLabel: 'Delete team',
                tone: 'danger',
            }))
        ) {
            return;
        }

        form.delete(teams.destroy.url(row.id), { preserveScroll: true });
    };

    return (
        <>
            <Head title="Support teams" />

            <div className="grid gap-6">
                <PageHeader
                    icon={UsersRound}
                    title="Support teams"
                    description="The desks an enquiry can be routed to, and the address that is told when one arrives."
                    actions={
                        <Button variant="outline" onClick={startCreate}>
                            New team
                        </Button>
                    }
                />

                <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_340px]">
                    <Card className="gap-0 overflow-hidden py-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Team</TableHead>
                                    <TableHead>Routing address</TableHead>
                                    <TableHead>Categories</TableHead>
                                    <TableHead>Conversations</TableHead>
                                    <TableHead />
                                </TableRow>
                            </TableHeader>

                            <TableBody>
                                {rows.map((row) => (
                                    <TableRow key={row.id}>
                                        <TableCell>
                                            <p className="font-medium">
                                                {row.name}
                                                {!row.is_active && (
                                                    <Badge
                                                        variant="muted"
                                                        className="ml-2"
                                                    >
                                                        Off
                                                    </Badge>
                                                )}
                                            </p>
                                            {row.description && (
                                                <p className="text-xs text-muted-foreground">
                                                    {row.description}
                                                </p>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-xs">
                                            {row.email ?? 'Not set'}
                                        </TableCell>
                                        <TableCell>
                                            {row.categories_count}
                                        </TableCell>
                                        <TableCell>{row.tickets_count}</TableCell>
                                        <TableCell className="text-right whitespace-nowrap">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => startEdit(row)}
                                            >
                                                <Pencil />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => void remove(row)}
                                            >
                                                <Trash2 />
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>

                        {rows.length === 0 && (
                            <p className="p-8 text-center text-sm text-muted-foreground">
                                No team yet. Add one so categories have somewhere
                                to route.
                            </p>
                        )}
                    </Card>
                    <Card className="h-fit">
                        <CardHeader>
                            <CardTitle className="text-sm">
                                {editing === null
                                    ? 'Add a team'
                                    : `Edit the ${editing.name} team`}
                            </CardTitle>
                        </CardHeader>

                        <CardContent className="grid gap-3">
                            <div className="grid gap-1">
                                <Label htmlFor="name">Name</Label>
                                <Input
                                    id="name"
                                    value={form.data.name}
                                    onChange={(event) =>
                                        form.setData('name', event.target.value)
                                    }
                                />
                                {form.errors.name && (
                                    <p className="text-xs text-destructive">
                                        {form.errors.name}
                                    </p>
                                )}
                            </div>

                            <div className="grid gap-1">
                                <Label htmlFor="email">Routing address</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    value={form.data.email}
                                    onChange={(event) =>
                                        form.setData('email', event.target.value)
                                    }
                                    placeholder="support@example.com"
                                />
                                {form.errors.email && (
                                    <p className="text-xs text-destructive">
                                        {form.errors.email}
                                    </p>
                                )}
                            </div>

                            <div className="grid gap-1">
                                <Label htmlFor="description">
                                    What the team handles
                                </Label>
                                <Input
                                    id="description"
                                    value={form.data.description}
                                    onChange={(event) =>
                                        form.setData(
                                            'description',
                                            event.target.value,
                                        )
                                    }
                                />
                            </div>

                            <div className="grid gap-1">
                                <Label htmlFor="sort_order">Order</Label>
                                <Input
                                    id="sort_order"
                                    type="number"
                                    min={0}
                                    value={form.data.sort_order}
                                    onChange={(event) =>
                                        form.setData(
                                            'sort_order',
                                            event.target.value,
                                        )
                                    }
                                />
                            </div>

                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={form.data.is_active}
                                    onChange={(event) =>
                                        form.setData(
                                            'is_active',
                                            event.target.checked,
                                        )
                                    }
                                    className="size-4 rounded border-input"
                                />
                                Receiving conversations
                            </label>

                            <div className="flex flex-wrap gap-2">
                                <Button
                                    type="button"
                                    onClick={submit}
                                    disabled={form.processing}
                                >
                                    {editing === null ? 'Add' : 'Save'}
                                </Button>

                                {editing !== null && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        onClick={startCreate}
                                    >
                                        Cancel
                                    </Button>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}
