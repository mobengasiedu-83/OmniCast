import { Head, router } from '@inertiajs/react';
import { Lock, Save, ShieldCheck } from 'lucide-react';
import { Fragment, useState } from 'react';
import PageHeader from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { urls } from '@/lib/urls';
import type { PermissionGroup, RoleRow } from '@/types';

type PageProps = {
    roles: RoleRow[];
    groups: PermissionGroup[];
};

export default function AdminRoles({ roles, groups }: PageProps) {
    const [draft, setDraft] = useState<Record<string, string[]>>(() =>
        Object.fromEntries(
            roles.map((role) => [role.name, [...role.permissions]]),
        ),
    );

    const toggle = (role: string, permission: string): void => {
        setDraft((current) => {
            const granted = current[role] ?? [];

            return {
                ...current,
                [role]: granted.includes(permission)
                    ? granted.filter((value) => value !== permission)
                    : [...granted, permission],
            };
        });
    };

    const isDirty = (role: RoleRow): boolean =>
        [...(draft[role.name] ?? [])].sort().join('|') !==
        [...role.permissions].sort().join('|');

    const save = (role: RoleRow): void => {
        router.put(
            urls.admin.roles.update(role.name),
            { permissions: draft[role.name] ?? [] },
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Roles" />

            <div className="flex flex-col gap-4">
                <PageHeader
                    icon={ShieldCheck}
                    title="Roles"
                    description="Grant each role the abilities it needs. Changes apply to every user holding the role."
                />

                <Card className="pb-0">
                    <CardHeader className="border-b border-border/60 py-3">
                        <CardTitle className="text-sm font-medium">
                            Permission matrix
                        </CardTitle>
                        <CardDescription className="text-xs">
                            Super Admin always holds every permission and cannot
                            be edited.
                        </CardDescription>
                    </CardHeader>

                    <CardContent className="px-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-72">
                                        Permission
                                    </TableHead>
                                    {roles.map((role) => (
                                        <TableHead
                                            key={role.name}
                                            className="text-center"
                                        >
                                            <span className="flex flex-col items-center gap-1">
                                                <span className="flex items-center gap-1 font-medium">
                                                    {role.label}
                                                    {role.locked && (
                                                        <Lock className="size-3" />
                                                    )}
                                                </span>
                                                <span className="text-[11px] font-normal text-muted-foreground">
                                                    {role.users_count}{' '}
                                                    {role.users_count === 1
                                                        ? 'user'
                                                        : 'users'}
                                                </span>
                                            </span>
                                        </TableHead>
                                    ))}
                                </TableRow>
                            </TableHeader>

                            <TableBody>
                                {groups.map((group) => (
                                    <Fragment key={group.key}>
                                        <TableRow className="bg-muted/40 hover:bg-muted/40">
                                            <TableCell
                                                colSpan={roles.length + 1}
                                                className="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                                            >
                                                {group.label}
                                            </TableCell>
                                        </TableRow>

                                        {group.permissions.map((permission) => (
                                            <TableRow key={permission.value}>
                                                <TableCell>
                                                    <p className="text-sm font-medium">
                                                        {permission.label}
                                                    </p>
                                                    <p className="font-mono text-[11px] text-muted-foreground">
                                                        {permission.value}
                                                    </p>
                                                </TableCell>

                                                {roles.map((role) => (
                                                    <TableCell
                                                        key={`${role.name}-${permission.value}`}
                                                        className="text-center"
                                                    >
                                                        <Checkbox
                                                            className="mx-auto"
                                                            checked={
                                                                role.locked ||
                                                                (
                                                                    draft[
                                                                        role
                                                                            .name
                                                                    ] ?? []
                                                                ).includes(
                                                                    permission.value,
                                                                )
                                                            }
                                                            disabled={
                                                                role.locked
                                                            }
                                                            onCheckedChange={() =>
                                                                toggle(
                                                                    role.name,
                                                                    permission.value,
                                                                )
                                                            }
                                                            aria-label={`${role.label}: ${permission.label}`}
                                                        />
                                                    </TableCell>
                                                ))}
                                            </TableRow>
                                        ))}
                                    </Fragment>
                                ))}
                                <TableRow className="hover:bg-transparent">
                                    <TableCell className="text-xs text-muted-foreground">
                                        Each column saves on its own.
                                    </TableCell>
                                    {roles.map((role) => (
                                        <TableCell
                                            key={`save-${role.name}`}
                                            className="text-center"
                                        >
                                            <Button
                                                size="sm"
                                                variant={
                                                    isDirty(role)
                                                        ? 'default'
                                                        : 'outline'
                                                }
                                                disabled={
                                                    role.locked ||
                                                    !isDirty(role)
                                                }
                                                onClick={() => save(role)}
                                            >
                                                <Save />
                                                {isDirty(role)
                                                    ? 'Save'
                                                    : 'Saved'}
                                            </Button>
                                        </TableCell>
                                    ))}
                                </TableRow>
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                <div className="flex flex-wrap gap-2">
                    {roles.map((role) => (
                        <Badge key={role.name} variant="outline">
                            {role.label}
                            <span className="ml-1 text-muted-foreground tabular-nums">
                                {draft[role.name]?.length ?? 0} permissions
                            </span>
                        </Badge>
                    ))}
                </div>
            </div>
        </>
    );
}

AdminRoles.layout = {
    breadcrumbs: [
        {
            title: 'Users',
            href: urls.admin.users.index,
        },
        {
            title: 'Roles',
            href: urls.admin.roles.index,
        },
    ],
};
