import { Head, router, useForm } from '@inertiajs/react';
import {
    MailPlus,
    RefreshCw,
    ShieldCheck,
    UserRoundX,
    UsersRound,
} from 'lucide-react';
import { useState, type FormEvent } from 'react';
import Field from '@/components/form-field';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
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
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { urls } from '@/lib/urls';
import type {
    SelectOption,
    TeamInvitationRow,
    TeamMemberRow,
} from '@/types';

type PageProps = {
    members: TeamMemberRow[];
    invitations: TeamInvitationRow[];
    roles: SelectOption[];
    stats: {
        members: number;
        pending: number;
        mfa: number;
    };
    can: {
        invite: boolean;
    };
};

export default function SettingsTeam({
    members,
    invitations,
    roles,
    stats,
    can,
}: PageProps) {
    const [inviteOpen, setInviteOpen] = useState(false);

    const form = useForm<{ email: string; name: string; role: string }>({
        email: '',
        name: '',
        role: roles[0]?.value ?? 'client-operator',
    });

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        form.post(urls.account.team.store, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setInviteOpen(false);
            },
        });
    };

    const resend = (invitation: TeamInvitationRow): void => {
        router.post(
            urls.account.team.resend(invitation.id),
            {},
            { preserveScroll: true },
        );
    };

    const revoke = (invitation: TeamInvitationRow): void => {
        router.delete(urls.account.team.destroy(invitation.id), {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Team" />

            <h1 className="sr-only">Team</h1>

            <div className="flex flex-wrap items-start justify-between gap-3">
                <Heading
                    variant="small"
                    title="Team"
                    description="Colleagues who can sign in to your organisation, and pending invitations."
                />

                {can.invite && (
                    <Button size="sm" onClick={() => setInviteOpen(true)}>
                        <MailPlus />
                        Invite colleague
                    </Button>
                )}
            </div>

            <div className="flex flex-wrap gap-2">
                <Badge variant="outline">{stats.members} members</Badge>
                <Badge variant="outline">{stats.pending} pending</Badge>
                <Badge variant="outline">{stats.mfa} with MFA</Badge>
            </div>

            <Card>
                <CardHeader className="border-b border-border/60 py-3">
                    <CardTitle className="flex items-center gap-2 text-sm font-medium">
                        <UsersRound className="size-4" />
                        Members
                    </CardTitle>
                </CardHeader>

                <CardContent className="divide-y divide-border/60 px-0">
                    {members.map((member) => (
                        <div
                            key={member.id}
                            className="flex flex-wrap items-center justify-between gap-3 px-4 py-3"
                        >
                            <div className="space-y-1">
                                <p className="flex flex-wrap items-center gap-2 text-sm font-medium">
                                    {member.name}
                                    {member.is_self && (
                                        <Badge variant="muted">You</Badge>
                                    )}
                                    {!member.is_active && (
                                        <Badge variant="destructive">
                                            Suspended
                                        </Badge>
                                    )}
                                </p>
                                <p className="text-muted-foreground text-xs">
                                    {member.email}
                                    {member.job_title
                                        ? ` · ${member.job_title}`
                                        : ''}
                                </p>
                            </div>

                            <div className="flex items-center gap-2">
                                {member.two_factor_enabled && (
                                    <Badge variant="success">
                                        <ShieldCheck className="size-3" />
                                        MFA
                                    </Badge>
                                )}
                                <Badge variant="secondary">
                                    {member.roles.join(', ') || 'No role'}
                                </Badge>
                            </div>
                        </div>
                    ))}
                </CardContent>
            </Card>

            <Card>
                <CardHeader className="border-b border-border/60 py-3">
                    <CardTitle className="flex items-center gap-2 text-sm font-medium">
                        <MailPlus className="size-4" />
                        Invitations
                    </CardTitle>
                    <CardDescription className="text-xs">
                        Invitees receive a one-time link that expires after
                        seven days.
                    </CardDescription>
                </CardHeader>

                <CardContent className="divide-y divide-border/60 px-0">
                    {invitations.length === 0 ? (
                        <p className="text-muted-foreground px-4 py-6 text-sm">
                            No invitations have been sent for this organisation.
                        </p>
                    ) : (
                        invitations.map((invitation) => (
                            <div
                                key={invitation.id}
                                className="flex flex-wrap items-center justify-between gap-3 px-4 py-3"
                            >
                                <div className="space-y-1">
                                    <p className="text-sm font-medium">
                                        {invitation.name ?? invitation.email}
                                    </p>
                                    <p className="text-muted-foreground text-xs">
                                        {invitation.email} ·{' '}
                                        {invitation.role_label}
                                        {invitation.expires_at
                                            ? ` · expires ${new Date(
                                                  invitation.expires_at,
                                              ).toLocaleDateString()}`
                                            : ''}
                                    </p>
                                </div>

                                <div className="flex items-center gap-2">
                                    <Badge
                                        variant={
                                            invitation.status === 'pending'
                                                ? 'warning'
                                                : invitation.status ===
                                                    'accepted'
                                                  ? 'success'
                                                  : 'muted'
                                        }
                                    >
                                        {invitation.status_label}
                                    </Badge>

                                    {invitation.is_pending && (
                                        <>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() =>
                                                    resend(invitation)
                                                }
                                            >
                                                <RefreshCw />
                                                Resend
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="text-destructive hover:text-destructive"
                                                onClick={() =>
                                                    revoke(invitation)
                                                }
                                            >
                                                <UserRoundX />
                                                Revoke
                                            </Button>
                                        </>
                                    )}
                                </div>
                            </div>
                        ))
                    )}
                </CardContent>
            </Card>


            <Dialog open={inviteOpen} onOpenChange={setInviteOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Invite a colleague</DialogTitle>
                        <DialogDescription>
                            They join this organisation only. You can revoke the
                            invitation until it is accepted.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={submit} className="space-y-4">
                        <Field
                            label="Email"
                            htmlFor="team-email"
                            error={form.errors.email}
                        >
                            <Input
                                id="team-email"
                                type="email"
                                value={form.data.email}
                                onChange={(event) =>
                                    form.setData('email', event.target.value)
                                }
                                placeholder="colleague@company.com"
                            />
                        </Field>

                        <Field
                            label="Name (optional)"
                            htmlFor="team-name"
                            error={form.errors.name}
                        >
                            <Input
                                id="team-name"
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                            />
                        </Field>

                        <div className="grid gap-2">
                            <Label>Role</Label>
                            <Select
                                value={form.data.role}
                                onValueChange={(value) =>
                                    form.setData('role', value)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select a role" />
                                </SelectTrigger>
                                <SelectContent>
                                    {roles.map((role) => (
                                        <SelectItem
                                            key={role.value}
                                            value={role.value}
                                        >
                                            {role.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <DialogFooter className="gap-2">
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() => setInviteOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                Send invitation
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

SettingsTeam.layout = {
    breadcrumbs: [
        {
            title: 'Team',
            href: urls.account.team.index,
        },
    ],
};

