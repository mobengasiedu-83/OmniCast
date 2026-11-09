import { Head, useForm } from '@inertiajs/react';
import { Pencil, Tags, Trash2 } from 'lucide-react';
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
import categories from '@/routes/admin/support/categories';
import type { SupportOption } from '@/types';

type CategoryRow = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    audience: string;
    audience_label: string;
    team_id: number | null;
    team: string | null;
    default_priority: string;
    default_priority_label: string;
    is_active: boolean;
    sort_order: number;
    tickets_count: number;
};

type PageProps = {
    categories: CategoryRow[];
    teams: SupportOption[];
    audiences: SupportOption[];
    priorities: SupportOption[];
};

type CategoryForm = {
    name: string;
    description: string;
    audience: string;
    support_team_id: string;
    default_priority: string;
    is_active: boolean;
    sort_order: string;
};

const BLANK: CategoryForm = {
    name: '',
    description: '',
    audience: 'both',
    support_team_id: '',
    default_priority: 'normal',
    is_active: true,
    sort_order: '0',
};

/**
 * The categories both forms offer.
 *
 * A category is configuration: adding one here puts it on the public contact form
 * and the client ticket form, with the team and priority it names. A category with
 * conversations against it can be switched off but not deleted, so history keeps
 * the reason it was filed under.
 */
export default function SupportCategories({
    categories: rows,
    teams,
    audiences,
    priorities,
}: PageProps) {
    const confirm = useConfirm();
    const [editing, setEditing] = useState<CategoryRow | null>(null);
    const form = useForm<CategoryForm>({ ...BLANK });

    const startCreate = (): void => {
        setEditing(null);
        form.setData({ ...BLANK });
        form.clearErrors();
    };

    const startEdit = (row: CategoryRow): void => {
        setEditing(row);
        form.setData({
            name: row.name,
            description: row.description ?? '',
            audience: row.audience,
            support_team_id: row.team_id === null ? '' : String(row.team_id),
            default_priority: row.default_priority,
            is_active: row.is_active,
            sort_order: String(row.sort_order),
        });
        form.clearErrors();
    };

    const submit = (): void => {
        if (editing !== null) {
            form.put(categories.update.url(editing.id), {
                preserveScroll: true,
                onSuccess: () => setEditing(null),
            });

            return;
        }

        form.post(categories.store.url(), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    const remove = async (row: CategoryRow): Promise<void> => {
        if (
            !(await confirm({
                title: `Delete “${row.name}”?`,
                description:
                    'A category with conversations against it cannot be deleted — switch it off instead.',
                confirmLabel: 'Delete category',
                tone: 'danger',
            }))
        ) {
            return;
        }

        form.delete(categories.destroy.url(row.id), { preserveScroll: true });
    };

    return (
        <>
            <Head title="Ticket categories" />

            <div className="grid gap-6">
                <PageHeader
                    icon={Tags}
                    title="Ticket categories"
                    description="The reasons a visitor or client can pick, and the team each one routes to."
                    actions={
                        <Button variant="outline" onClick={startCreate}>
                            New category
                        </Button>
                    }
                />

                <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_340px]">
                    <Card className="gap-0 overflow-hidden py-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Category</TableHead>
                                    <TableHead>Offered on</TableHead>
                                    <TableHead>Team</TableHead>
                                    <TableHead>Priority</TableHead>
                                    <TableHead>Filed</TableHead>
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
                                            {row.audience_label}
                                        </TableCell>
                                        <TableCell>
                                            {row.team ?? 'Unrouted'}
                                        </TableCell>
                                        <TableCell>
                                            {row.default_priority_label}
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
                                No category yet. Add one so the forms have
                                something to offer.
                            </p>
                        )}
                    </Card>
                    <Card className="h-fit">
                        <CardHeader>
                            <CardTitle className="text-sm">
                                {editing === null
                                    ? 'Add a category'
                                    : `Edit “${editing.name}”`}
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
                                <Label htmlFor="description">
                                    Short description
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
                                {form.errors.description && (
                                    <p className="text-xs text-destructive">
                                        {form.errors.description}
                                    </p>
                                )}
                            </div>

                            <div className="grid gap-1">
                                <Label htmlFor="audience">Offered on</Label>
                                <select
                                    id="audience"
                                    value={form.data.audience}
                                    onChange={(event) =>
                                        form.setData(
                                            'audience',
                                            event.target.value,
                                        )
                                    }
                                    className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                                >
                                    {audiences.map((option) => (
                                        <option
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </option>
                                    ))}
                                </select>
                                {form.errors.audience && (
                                    <p className="text-xs text-destructive">
                                        {form.errors.audience}
                                    </p>
                                )}
                            </div>

                            <div className="grid gap-1">
                                <Label htmlFor="support_team_id">Team</Label>
                                <select
                                    id="support_team_id"
                                    value={form.data.support_team_id}
                                    onChange={(event) =>
                                        form.setData(
                                            'support_team_id',
                                            event.target.value,
                                        )
                                    }
                                    className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                                >
                                    <option value="">Unrouted</option>
                                    {teams.map((option) => (
                                        <option
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="grid gap-1">
                                <Label htmlFor="default_priority">
                                    Default priority
                                </Label>
                                <select
                                    id="default_priority"
                                    value={form.data.default_priority}
                                    onChange={(event) =>
                                        form.setData(
                                            'default_priority',
                                            event.target.value,
                                        )
                                    }
                                    className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                                >
                                    {priorities.map((option) => (
                                        <option
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </option>
                                    ))}
                                </select>
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
                                Offered on the forms
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
