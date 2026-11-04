import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Ban,
    CheckCircle2,
    Copy,
    Megaphone,
    Pencil,
    ShieldCheck,
    UserRound,
} from 'lucide-react';
import EmptyState from '@/components/empty-state';
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
    ContactCampaignHistory,
    ContactDetail,
    ContactEligibilitySnapshot,
} from '@/types';

type PageProps = {
    contact: ContactDetail;
    eligibility: Record<string, ContactEligibilitySnapshot>;
    campaigns: ContactCampaignHistory[];
    can: {
        update: boolean;
        delete: boolean;
    };
    dndRequestUrl: string | null;
};

/** The reasons a channel cannot be used are shown verbatim to the operator. */
const reasonLabels: Record<string, string> = {
    dnd: 'Number is on the DND list',
    suppressed: 'Number or address is on the DND registry',
};

function moment(value: string | null): string {
    return value
        ? new Date(value).toLocaleString(undefined, {
              dateStyle: 'medium',
              timeStyle: 'short',
          })
        : '—';
}

export default function ContactsShow({
    contact,
    eligibility,
    campaigns,
    can,
    dndRequestUrl,
}: PageProps) {
    const channels = Object.keys(eligibility);

    const toggleDnd = (): void => {
        router.post(
            urls.contacts.dnd(contact.id),
            { dnd: !contact.dnd },
            { preserveScroll: true },
        );
    };

    const copyDndRequest = (): void => {
        if (dndRequestUrl !== null) {
            void navigator.clipboard.writeText(dndRequestUrl);
        }
    };

    const profile: { label: string; value: string }[] = [
        { label: 'Phone', value: contact.phone },
        { label: 'Alternate phone', value: contact.alternative_phone ?? '—' },
        { label: 'Email', value: contact.email ?? '—' },
        { label: 'Company', value: contact.company ?? '—' },
        { label: 'Account number', value: contact.account_number ?? '—' },
        { label: 'Customer ID', value: contact.customer_id ?? '—' },
        { label: 'Location', value: contact.location ?? '—' },
        { label: 'Language', value: contact.language ?? '—' },
        { label: 'Timezone', value: contact.timezone ?? '—' },
        { label: 'Added by', value: contact.creator ?? '—' },
        { label: 'Created', value: moment(contact.created_at) },
        { label: 'Updated', value: moment(contact.updated_at) },
    ];

    return (
        <>
            <Head title={contact.name} />

            <div className="flex flex-col gap-4">
                <PageHeader
                    icon={UserRound}
                    title={contact.name}
                    description={`${contact.phone}${contact.email ? ` · ${contact.email}` : ''}`}
                    badge={
                        contact.dnd ? (
                            <Badge variant="destructive">DND</Badge>
                        ) : (
                            <Badge variant="success">Reachable</Badge>
                        )
                    }
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <Link href={urls.contacts.index}>
                                    <ArrowLeft />
                                    Contacts
                                </Link>
                            </Button>

                            {can.update && (
                                <Button asChild>
                                    <Link href={urls.contacts.edit(contact.id)}>
                                        <Pencil />
                                        Edit contact
                                    </Link>
                                </Button>
                            )}
                        </>
                    }
                />

                <div className="grid gap-4 lg:grid-cols-3">
                    <div className="flex flex-col gap-4 lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-sm font-medium">
                                    <UserRound className="text-brand size-4" />
                                    Profile
                                </CardTitle>
                                <CardDescription>
                                    Everything campaigns can merge into a
                                    message.
                                </CardDescription>
                            </CardHeader>

                            <CardContent className="grid gap-x-6 gap-y-3 sm:grid-cols-2 lg:grid-cols-3">
                                {profile.map((field) => (
                                    <div key={field.label} className="min-w-0">
                                        <p className="text-muted-foreground text-xs">
                                            {field.label}
                                        </p>
                                        <p className="truncate text-sm font-medium">
                                            {field.value}
                                        </p>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>


                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-sm font-medium">
                                    <ShieldCheck className="text-brand size-4" />
                                    Channel eligibility
                                </CardTitle>
                                <CardDescription>
                                    Eligibility is re-checked against the DND
                                    registry before every send.
                                </CardDescription>
                            </CardHeader>

                            <CardContent className="flex flex-col gap-3">
                                {channels.map((channel) => {
                                    const snapshot = eligibility[channel];

                                    return (
                                        <div
                                            key={channel}
                                            className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-border/60 px-3 py-2"
                                        >
                                            <span className="text-sm font-medium uppercase">
                                                {channel}
                                            </span>

                                            {snapshot?.eligible ? (
                                                <span className="text-success flex items-center gap-1.5 text-xs font-medium">
                                                    <CheckCircle2 className="size-3.5" />
                                                    Eligible
                                                </span>
                                            ) : (
                                                <span className="text-muted-foreground flex items-center gap-1.5 text-xs">
                                                    <Ban className="size-3.5" />
                                                    {reasonLabels[
                                                        snapshot?.reason ?? ''
                                                    ] ?? 'Not eligible'}
                                                </span>
                                            )}
                                        </div>
                                    );
                                })}
                            </CardContent>
                        </Card>
                    </div>

                    <div className="flex flex-col gap-4">

                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-sm font-medium">
                                    <Ban className="text-brand size-4" />
                                    Do not disturb
                                </CardTitle>
                                <CardDescription>
                                    DND blocks SMS, voice and email and keeps
                                    the number in the registry.
                                </CardDescription>
                            </CardHeader>

                            <CardContent className="flex flex-col gap-3">
                                <div className="flex items-center justify-between gap-2">
                                    <span className="text-muted-foreground text-xs">
                                        Status
                                    </span>
                                    {contact.dnd ? (
                                        <Badge variant="destructive">
                                            On the DND list
                                        </Badge>
                                    ) : (
                                        <Badge variant="success">
                                            Reachable
                                        </Badge>
                                    )}
                                </div>

                                {contact.dnd_at !== null && (
                                    <div className="flex items-center justify-between gap-2">
                                        <span className="text-muted-foreground text-xs">
                                            Since
                                        </span>
                                        <span className="text-sm">
                                            {moment(contact.dnd_at)}
                                        </span>
                                    </div>
                                )}

                                {can.update && (
                                    <Button
                                        variant={
                                            contact.dnd
                                                ? 'outline'
                                                : 'destructive'
                                        }
                                        onClick={toggleDnd}
                                    >
                                        <Ban />
                                        {contact.dnd
                                            ? 'Remove from DND list'
                                            : 'Add to DND list'}
                                    </Button>
                                )}
                            </CardContent>
                        </Card>



                        <Card>
                            <CardHeader>
                                <CardTitle className="text-sm font-medium">
                                    Audience
                                </CardTitle>
                            </CardHeader>

                            <CardContent className="flex flex-col gap-3 text-sm">
                                <div>
                                    <p className="text-muted-foreground text-xs">
                                        Lists
                                    </p>
                                    <div className="mt-1 flex flex-wrap gap-1">
                                        {contact.lists.length > 0 ? (
                                            contact.lists.map((list) => (
                                                <Badge
                                                    key={list}
                                                    variant="outline"
                                                >
                                                    {list}
                                                </Badge>
                                            ))
                                        ) : (
                                            <span className="text-muted-foreground">
                                                Not in a list yet
                                            </span>
                                        )}
                                    </div>
                                </div>

                                <div>
                                    <p className="text-muted-foreground text-xs">
                                        Tags
                                    </p>
                                    <div className="mt-1 flex flex-wrap gap-1">
                                        {contact.tags.length > 0 ? (
                                            contact.tags.map((tag) => (
                                                <Badge
                                                    key={tag.id}
                                                    variant="outline"
                                                >
                                                    {tag.name}
                                                </Badge>
                                            ))
                                        ) : (
                                            <span className="text-muted-foreground">
                                                No tags
                                            </span>
                                        )}
                                    </div>
                                </div>

                                {can.delete && (
                                    <Button
                                        variant="destructive"
                                        onClick={() => {
                                            if (
                                                window.confirm(
                                                    `Delete ${contact.name}? The DND registry keeps the number blocked if it is imported again.`,
                                                )
                                            ) {
                                                router.delete(
                                                    urls.contacts.destroy(
                                                        contact.id,
                                                    ),
                                                );
                                            }
                                        }}
                                    >
                                        Delete contact
                                    </Button>
                                )}
                            </CardContent>
                        </Card>

                        {dndRequestUrl !== null && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-sm font-medium">
                                        Stop-messages link
                                    </CardTitle>
                                    <CardDescription>
                                        Signed and valid for 60 days. Anyone
                                        with the link can add the number to the
                                        DND list.
                                    </CardDescription>
                                </CardHeader>

                                <CardContent className="flex flex-col gap-2">
                                    <p className="text-muted-foreground bg-muted/50 max-h-20 overflow-y-auto rounded-md p-2 font-mono text-xs break-all">
                                        {dndRequestUrl}
                                    </p>
                                    <Button
                                        variant="outline"
                                        onClick={copyDndRequest}
                                    >
                                        <Copy />
                                        Copy link
                                    </Button>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-sm font-medium">
                            <Megaphone className="text-brand size-4" />
                            Campaign history
                        </CardTitle>
                        <CardDescription>
                            The last 10 campaigns this contact was included
                            in.
                        </CardDescription>
                    </CardHeader>

                    <CardContent className="px-0">
                        {campaigns.length === 0 ? (
                            <EmptyState
                                icon={Megaphone}
                                title="Not targeted yet"
                                description="Once a campaign audience matches this contact it will be listed here."
                                action={
                                    <Button variant="outline" asChild>
                                        <Link href={urls.campaigns.index}>
                                            Browse campaigns
                                        </Link>
                                    </Button>
                                }
                            />
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="pl-6">
                                            Campaign
                                        </TableHead>
                                        <TableHead>Channel</TableHead>
                                        <TableHead className="pr-6">
                                            Status
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>

                                <TableBody>
                                    {campaigns.map((campaign) => (
                                        <TableRow key={campaign.id}>
                                            <TableCell className="pl-6">
                                                <Link
                                                    href={urls.campaigns.show(
                                                        campaign.id,
                                                    )}
                                                    className="hover:text-brand font-medium hover:underline"
                                                >
                                                    {campaign.name}
                                                </Link>
                                                <span className="text-muted-foreground block font-mono text-xs">
                                                    {campaign.reference}
                                                </span>
                                            </TableCell>
                                            <TableCell className="text-muted-foreground text-xs uppercase">
                                                {campaign.channel}
                                            </TableCell>
                                            <TableCell className="pr-6">
                                                <Badge variant="outline">
                                                    {campaign.status.replace(
                                                        /_/g,
                                                        ' ',
                                                    )}
                                                </Badge>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

ContactsShow.layout = {
    breadcrumbs: [
        {
            title: 'Contacts',
            href: urls.contacts.index,
        },
        {
            title: 'Contact detail',
            href: urls.contacts.index,
        },
    ],
};

