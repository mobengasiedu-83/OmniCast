import { Head } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import PageHeader from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { urls } from '@/lib/urls';
import type { PermissionGroup, SelectOption } from '@/types';

type PageProps = {
    groups: PermissionGroup[];
    roles: SelectOption[];
};

export default function AdminPermissions({ groups, roles }: PageProps) {
    const total = groups.reduce(
        (count, group) => count + group.permissions.length,
        0,
    );

    return (
        <>
            <Head title="Permissions" />

            <div className="flex flex-col gap-4">
                <PageHeader
                    icon={ShieldCheck}
                    title="Permissions"
                    description="Every ability the platform checks, and the roles that currently hold it."
                />

                <div className="flex flex-wrap gap-2">
                    <Badge variant="outline">{total} permissions</Badge>
                    {roles.map((role) => (
                        <Badge key={role.value} variant="outline">
                            {role.label}
                        </Badge>
                    ))}
                </div>

                <Card className="overflow-hidden">
                    <CardHeader className="border-b border-border/60 py-3">
                        <CardTitle className="text-sm font-medium">
                            Permission catalogue
                        </CardTitle>
                        <CardDescription className="text-xs">
                            Edit the grants on the roles screen.
                        </CardDescription>
                    </CardHeader>

                    <CardContent className="px-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Permission</TableHead>
                                    <TableHead>Key</TableHead>
                                    <TableHead>Held by</TableHead>
                                </TableRow>
                            </TableHeader>

                            <TableBody>
                                {groups.map((group) => (
                                    <GroupRows key={group.key} group={group} />
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function GroupRows({ group }: { group: PermissionGroup }) {
    return (
        <>
            <TableRow className="bg-muted/40 hover:bg-muted/40">
                <TableCell
                    colSpan={3}
                    className="text-muted-foreground text-xs font-semibold tracking-wide uppercase"
                >
                    {group.label}
                </TableCell>
            </TableRow>

            {group.permissions.map((permission) => (
                <TableRow key={permission.value}>
                    <TableCell className="font-medium">
                        {permission.label}
                    </TableCell>
                    <TableCell className="text-muted-foreground font-mono text-xs">
                        {permission.value}
                    </TableCell>
                    <TableCell>
                        <div className="flex flex-wrap gap-1">
                            {(permission.roles ?? []).length === 0 ? (
                                <span className="text-muted-foreground text-xs">
                                    Nobody
                                </span>
                            ) : (
                                (permission.roles ?? []).map((role) => (
                                    <Badge key={role} variant="secondary">
                                        {role}
                                    </Badge>
                                ))
                            )}
                        </div>
                    </TableCell>
                </TableRow>
            ))}
        </>
    );
}

AdminPermissions.layout = {
    breadcrumbs: [
        {
            title: 'Users',
            href: urls.admin.users.index,
        },
        {
            title: 'Permissions',
            href: urls.admin.permissions.index,
        },
    ],
};
