import { Head, router, useForm } from '@inertiajs/react';
import {
    AtSign,
    BarChart3,
    CopyCheck,
    Mail,
    Plus,
    Tags,
    Users,
} from 'lucide-react';
import { useState, type FormEvent } from 'react';
import ClientFilter from '@/components/client-filter';
import EmptyState from '@/components/empty-state';
import PageHeader from '@/components/page-header';
import StatCard from '@/components/stat-card';
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
import { urls } from '@/lib/urls';
import type {
    ClientOption,
    ContactDuplicateGroup,
    ContactListOption,
    ContactQualityReport,
    ContactSegmentRow,
    TagOption,
} from '@/types';

type PageProps = {
    quality: ContactQualityReport;
    duplicateGroups: ContactDuplicateGroup[];
    segments: ContactSegmentRow[];
    tags: (TagOption & { contacts_count: number })[];
    lists: ContactListOption[];
    clients: ClientOption[];
    clientId: number;
};

/** The saved segment filters the backend understands. */
const emptyFilters = {
    search: '',
    dnd: '',
    location: '',
    company: '',
    contact_list_id: '',
    tag_id: '',
    has_email: '',
};

export default function ContactAudiences({
    quality,
    duplicateGroups,
    segments,
    tags,
    lists,
    clients,
    clientId,
}: PageProps) {
    const tagForm = useForm({ name: '', color: 'slate' });
    const listForm = useForm({ name: '', description: '' });
    const segmentForm = useForm({
        name: '',
        description: '',
        filters: { ...emptyFilters },
    });
    const editForm = useForm({ name: '', description: '' });
    const [editingSegment, setEditingSegment] = useState<number | null>(null);
    const [survivors, setSurvivors] = useState<Record<string, number>>({});

    const submitTag = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        tagForm.post(urls.contacts.storeTag, {
            preserveScroll: true,
            onSuccess: () => tagForm.reset('name'),
        });
    };

    const submitList = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        listForm.post(urls.contacts.storeList, {
            preserveScroll: true,
            onSuccess: () => listForm.reset(),
        });
    };

    const submitSegment = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        const filters = Object.fromEntries(
            Object.entries(segmentForm.data.filters).filter(
                ([, value]) => value !== '',
            ),
        );

        segmentForm.transform((data) => ({
            ...data,
            filters: Object.fromEntries(
                Object.entries(filters).map(([key, value]) =>
                    ['contact_list_id', 'tag_id'].includes(key)
                        ? [key, Number(value)]
                        : key === 'has_email'
                          ? [key, value === 'yes']
                          : [key, value],
                ),
            ),
        }));

        segmentForm.post(urls.contacts.storeSegment, {
            preserveScroll: true,
            onSuccess: () =>
                segmentForm.reset('name', 'description', 'filters'),
        });
    };

    const startEditing = (segment: ContactSegmentRow): void => {
        setEditingSegment(segment.id);
        editForm.setData({
            name: segment.name,
            description: segment.description ?? '',
        });
    };

    const submitSegmentEdit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        if (editingSegment === null) {
            return;
        }

        editForm.put(urls.contacts.updateSegment(editingSegment), {
            preserveScroll: true,
            onSuccess: () => setEditingSegment(null),
        });
    };

    const mergeInto = (
        email: string,
        survivorId: number,
        duplicateId: number,
    ): void => {
        router.post(
            urls.contacts.mergeDuplicates,
            { survivor_id: survivorId, duplicate_id: duplicateId },
            {
                preserveScroll: true,
                onSuccess: () =>
                    setSurvivors((current) => {
                        const next = { ...current };
                        delete next[email];

                        return next;
                    }),
            },
        );
    };

    return (
        <>
            <Head title="Audiences" />

            <div className="flex flex-col gap-4">
                <PageHeader
                    icon={Users}
                    title="Audiences"
                    description="Audit contact quality, merge duplicates and build the lists, tags and segments campaigns send to."
                    actions={
                        <ClientFilter
                            clients={clients}
                            value={clientId}
                            query={{}}
                        />
                    }
                />

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        title="Contacts"
                        value={quality.total}
                        description={`${quality.broadcastable} broadcastable`}
                        icon={Users}
                        tone="brand"
                    />
                    <StatCard
                        title="Missing email"
                        value={quality.missing_email}
                        description={`${quality.invalid_email} invalid addresses`}
                        icon={Mail}
                        tone={quality.missing_email > 0 ? 'warning' : 'success'}
                    />
                    <StatCard
                        title="Duplicate emails"
                        value={quality.duplicate_contacts}
                        description={`${quality.duplicate_emails} shared addresses`}
                        icon={AtSign}
                        tone={
                            quality.duplicate_contacts > 0
                                ? 'danger'
                                : 'success'
                        }
                    />
                    <StatCard
                        title="Added last 30 days"
                        value={quality.added_last_30_days}
                        description="New contacts in the book"
                        icon={Plus}
                        tone="signal"
                    />
                    <StatCard
                        title="Broadcastable"
                        value={quality.broadcastable}
                        description="Not on the DND list"
                        icon={BarChart3}
                        tone="success"
                    />
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-sm font-medium">
                                <Tags className="size-4 text-brand" />
                                Tags
                            </CardTitle>
                            <CardDescription>
                                Lightweight labels used to narrow audiences.
                            </CardDescription>
                        </CardHeader>

                        <CardContent className="flex flex-col gap-3">
                            <form
                                onSubmit={submitTag}
                                className="flex flex-wrap items-end gap-2"
                            >
                                <div className="grid flex-1 gap-1.5">
                                    <Label htmlFor="tag-name">Tag name</Label>
                                    <Input
                                        id="tag-name"
                                        value={tagForm.data.name}
                                        onChange={(event) =>
                                            tagForm.setData(
                                                'name',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="VIP"
                                        required
                                    />
                                </div>
                                <Button
                                    type="submit"
                                    disabled={tagForm.processing}
                                >
                                    <Plus />
                                    Add
                                </Button>
                            </form>

                            {tags.length === 0 ? (
                                <p className="text-xs text-muted-foreground">
                                    No tags yet.
                                </p>
                            ) : (
                                <div className="flex flex-wrap gap-2">
                                    {tags.map((tag) => (
                                        <span
                                            key={tag.id}
                                            className="flex items-center gap-1.5 rounded-full border border-border/60 py-1 pr-1 pl-3 text-xs"
                                        >
                                            <span className="font-medium">
                                                {tag.name}
                                            </span>
                                            <span className="text-muted-foreground">
                                                {tag.contacts_count}
                                            </span>
                                            <button
                                                type="button"
                                                aria-label={`Remove ${tag.name}`}
                                                onClick={() =>
                                                    router.delete(
                                                        urls.contacts.deleteTag(
                                                            tag.id,
                                                        ),
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                                className="px-1 text-muted-foreground hover:text-destructive"
                                            >
                                                ×
                                            </button>
                                        </span>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-sm font-medium">
                                <Users className="size-4 text-brand" />
                                Contact lists
                            </CardTitle>
                            <CardDescription>
                                Static groups you can add contacts to from the
                                contact record.
                            </CardDescription>
                        </CardHeader>

                        <CardContent className="flex flex-col gap-3">
                            <form
                                onSubmit={submitList}
                                className="flex flex-wrap items-end gap-2"
                            >
                                <div className="grid flex-1 gap-1.5">
                                    <Label htmlFor="list-name">List name</Label>
                                    <Input
                                        id="list-name"
                                        value={listForm.data.name}
                                        onChange={(event) =>
                                            listForm.setData(
                                                'name',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Accra retail"
                                        required
                                    />
                                </div>
                                <Button
                                    type="submit"
                                    disabled={listForm.processing}
                                >
                                    <Plus />
                                    Add
                                </Button>
                            </form>

                            {lists.length === 0 ? (
                                <p className="text-xs text-muted-foreground">
                                    No contact lists yet.
                                </p>
                            ) : (
                                <div className="flex flex-col divide-y divide-border/60">
                                    {lists.map((list) => (
                                        <div
                                            key={list.id}
                                            className="flex items-center justify-between gap-2 py-2 text-sm first:pt-0 last:pb-0"
                                        >
                                            <span className="flex items-center gap-2">
                                                <span className="font-medium">
                                                    {list.name}
                                                </span>
                                            </span>
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                onClick={() =>
                                                    router.delete(
                                                        urls.contacts.deleteList(
                                                            list.id,
                                                        ),
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                Delete
                                            </Button>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-sm font-medium">
                            <CopyCheck className="size-4 text-brand" />
                            Duplicate contacts
                        </CardTitle>
                        <CardDescription>
                            Contacts sharing an email address. Pick the record
                            to keep, then merge the rest into it.
                        </CardDescription>
                    </CardHeader>

                    <CardContent className="flex flex-col gap-3">
                        {duplicateGroups.length === 0 && (
                            <EmptyState
                                icon={CopyCheck}
                                title="No duplicate emails"
                                description="Every contact has its own email address."
                            />
                        )}

                        {duplicateGroups.map((group) => {
                            const survivor =
                                survivors[group.email] ?? group.contacts[0]?.id;

                            return (
                                <div
                                    key={group.email}
                                    className="flex flex-col gap-2 rounded-lg border border-border/60 p-3"
                                >
                                    <p className="text-sm font-medium break-all">
                                        {group.email}
                                    </p>

                                    <Select
                                        value={String(survivor)}
                                        onValueChange={(value) =>
                                            setSurvivors((current) => ({
                                                ...current,
                                                [group.email]: Number(value),
                                            }))
                                        }
                                    >
                                        <SelectTrigger
                                            className="w-full"
                                            aria-label="Record to keep"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {group.contacts.map((member) => (
                                                <SelectItem
                                                    key={member.id}
                                                    value={String(member.id)}
                                                >
                                                    {member.name} ·{' '}
                                                    {member.phone}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>

                                    <div className="flex flex-col gap-2">
                                        {group.contacts
                                            .filter(
                                                (member) =>
                                                    member.id !== survivor,
                                            )
                                            .map((member) => (
                                                <div
                                                    key={member.id}
                                                    className="flex items-center justify-between gap-2 text-sm"
                                                >
                                                    <span className="font-mono text-xs text-muted-foreground">
                                                        {member.name} ·{' '}
                                                        {member.phone}
                                                    </span>
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() =>
                                                            mergeInto(
                                                                group.email,
                                                                survivor,
                                                                member.id,
                                                            )
                                                        }
                                                    >
                                                        Merge in
                                                    </Button>
                                                </div>
                                            ))}
                                    </div>
                                </div>
                            );
                        })}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-sm font-medium">
                            <Users className="size-4 text-brand" />
                            Audience segments
                        </CardTitle>
                        <CardDescription>
                            Save a filter combination once and reuse it when
                            building campaign audiences.
                        </CardDescription>
                    </CardHeader>

                    <CardContent className="flex flex-col gap-4">
                        <form
                            onSubmit={submitSegment}
                            className="grid gap-3 sm:grid-cols-2"
                        >
                            <div className="grid gap-1.5">
                                <Label htmlFor="segment-name">Name</Label>
                                <Input
                                    id="segment-name"
                                    value={segmentForm.data.name}
                                    onChange={(event) =>
                                        segmentForm.setData(
                                            'name',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Accra retail · opted in"
                                    required
                                />
                                {segmentForm.errors.name && (
                                    <p className="text-xs text-destructive">
                                        {segmentForm.errors.name}
                                    </p>
                                )}
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="segment-description">
                                    Description
                                </Label>
                                <Input
                                    id="segment-description"
                                    value={segmentForm.data.description}
                                    onChange={(event) =>
                                        segmentForm.setData(
                                            'description',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Optional note for the team"
                                />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="segment-search">
                                    Search text
                                </Label>
                                <Input
                                    id="segment-search"
                                    value={segmentForm.data.filters.search}
                                    onChange={(event) =>
                                        segmentForm.setData('filters', {
                                            ...segmentForm.data.filters,
                                            search: event.target.value,
                                        })
                                    }
                                    placeholder="Name, phone or email"
                                />
                            </div>

                            <div className="grid gap-1.5">
                                <Label>DND</Label>
                                <Select
                                    value={
                                        segmentForm.data.filters.dnd || 'any'
                                    }
                                    onValueChange={(value) =>
                                        segmentForm.setData('filters', {
                                            ...segmentForm.data.filters,
                                            dnd: value === 'any' ? '' : value,
                                        })
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="any">
                                            Any contact
                                        </SelectItem>
                                        <SelectItem value="1">
                                            On the DND list
                                        </SelectItem>
                                        <SelectItem value="0">
                                            Reachable
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="segment-location">
                                    Location
                                </Label>
                                <Input
                                    id="segment-location"
                                    value={segmentForm.data.filters.location}
                                    onChange={(event) =>
                                        segmentForm.setData('filters', {
                                            ...segmentForm.data.filters,
                                            location: event.target.value,
                                        })
                                    }
                                    placeholder="Accra"
                                />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="segment-company">Company</Label>
                                <Input
                                    id="segment-company"
                                    value={segmentForm.data.filters.company}
                                    onChange={(event) =>
                                        segmentForm.setData('filters', {
                                            ...segmentForm.data.filters,
                                            company: event.target.value,
                                        })
                                    }
                                    placeholder="Company or organisation"
                                />
                            </div>

                            <div className="grid gap-1.5">
                                <Label>Contact list</Label>
                                <Select
                                    value={
                                        segmentForm.data.filters
                                            .contact_list_id || 'any'
                                    }
                                    onValueChange={(value) =>
                                        segmentForm.setData('filters', {
                                            ...segmentForm.data.filters,
                                            contact_list_id:
                                                value === 'any' ? '' : value,
                                        })
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="any">
                                            Any list
                                        </SelectItem>
                                        {lists.map((list) => (
                                            <SelectItem
                                                key={list.id}
                                                value={String(list.id)}
                                            >
                                                {list.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="grid gap-1.5">
                                <Label>Tag</Label>
                                <Select
                                    value={
                                        segmentForm.data.filters.tag_id || 'any'
                                    }
                                    onValueChange={(value) =>
                                        segmentForm.setData('filters', {
                                            ...segmentForm.data.filters,
                                            tag_id:
                                                value === 'any' ? '' : value,
                                        })
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="any">
                                            Any tag
                                        </SelectItem>
                                        {tags.map((tag) => (
                                            <SelectItem
                                                key={tag.id}
                                                value={String(tag.id)}
                                            >
                                                {tag.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="grid gap-1.5">
                                <Label>Email address</Label>
                                <Select
                                    value={
                                        segmentForm.data.filters.has_email ||
                                        'any'
                                    }
                                    onValueChange={(value) =>
                                        segmentForm.setData('filters', {
                                            ...segmentForm.data.filters,
                                            has_email:
                                                value === 'any' ? '' : value,
                                        })
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="any">
                                            Any contact
                                        </SelectItem>
                                        <SelectItem value="yes">
                                            Has an email
                                        </SelectItem>
                                        <SelectItem value="no">
                                            No email
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="flex items-end sm:col-span-2">
                                <Button
                                    type="submit"
                                    disabled={segmentForm.processing}
                                >
                                    <Plus />
                                    Save segment
                                </Button>
                            </div>
                        </form>

                        {segments.length === 0 && (
                            <EmptyState
                                icon={Users}
                                title="No saved segments"
                                description="Save a filter combination above to reuse it across campaigns."
                            />
                        )}

                        <div className="flex flex-col divide-y divide-border/60">
                            {segments.map((segment) => (
                                <div
                                    key={segment.id}
                                    className="flex flex-col gap-2 py-3 first:pt-0 last:pb-0"
                                >
                                    {editingSegment === segment.id ? (
                                        <form
                                            onSubmit={submitSegmentEdit}
                                            className="grid gap-2 sm:grid-cols-[1fr_1fr_auto]"
                                        >
                                            <Input
                                                value={editForm.data.name}
                                                onChange={(event) =>
                                                    editForm.setData(
                                                        'name',
                                                        event.target.value,
                                                    )
                                                }
                                                aria-label="Segment name"
                                                required
                                            />
                                            <Input
                                                value={
                                                    editForm.data.description
                                                }
                                                onChange={(event) =>
                                                    editForm.setData(
                                                        'description',
                                                        event.target.value,
                                                    )
                                                }
                                                aria-label="Segment description"
                                                placeholder="Description"
                                            />
                                            <div className="flex gap-2">
                                                <Button
                                                    type="submit"
                                                    size="sm"
                                                    disabled={
                                                        editForm.processing
                                                    }
                                                >
                                                    Save
                                                </Button>
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() =>
                                                        setEditingSegment(null)
                                                    }
                                                >
                                                    Cancel
                                                </Button>
                                            </div>
                                        </form>
                                    ) : (
                                        <div className="flex flex-wrap items-start justify-between gap-2">
                                            <div className="space-y-0.5">
                                                <p className="text-sm font-medium">
                                                    {segment.name}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {segment.contacts_count}{' '}
                                                    contacts match
                                                    {segment.description
                                                        ? ` · ${segment.description}`
                                                        : ''}
                                                </p>
                                            </div>

                                            <div className="flex gap-2">
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        startEditing(segment)
                                                    }
                                                >
                                                    Edit
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() =>
                                                        router.delete(
                                                            urls.contacts.deleteSegment(
                                                                segment.id,
                                                            ),
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    Delete
                                                </Button>
                                            </div>
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>

            </div>
        </>
    );
}

ContactAudiences.layout = {
    breadcrumbs: [
        {
            title: 'Contacts',
            href: urls.contacts.index,
        },
        {
            title: 'Audiences',
            href: urls.contacts.audiences,
        },
    ],
};
