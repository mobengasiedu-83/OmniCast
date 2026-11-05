import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Download, FileUp, Plus, Upload, Users } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import InputError from '@/components/input-error';
import PageHeader from '@/components/page-header';
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
import type { ClientOption, ContactListOption } from '@/types';

type PageProps = {
    contactLists: ContactListOption[];
    clients: ClientOption[];
};

/** The columns the importer understands, in the order staff should supply them. */
const columns: { name: string; description: string }[] = [
    {
        name: 'phone',
        description:
            'Required MSISDN. Also read from "phone_number", "msisdn" or "mobile".',
    },
    {
        name: 'first_name',
        description:
            'Optional. Falls back to a "name" column, then to the MSISDN.',
    },
    { name: 'last_name', description: 'Optional surname.' },
    {
        name: 'email',
        description:
            'Optional. Stored lower-cased and used for duplicate detection.',
    },
    {
        name: 'company, account_number, customer_id, location, language, timezone',
        description: 'Copied onto the contact when present.',
    },
    {
        name: 'dnd',
        description:
            'Yes, true or 1 adds the number to the DND list and blocks every channel.',
    },
    {
        name: 'tags',
        description: 'Comma separated tag names; missing tags are created.',
    },
    {
        name: 'custom_*, e.g. custom_loyalty_tier',
        description: 'Becomes a custom field named after the suffix.',
    },
];

export default function ContactsImport({ contactLists, clients }: PageProps) {
    const form = useForm<{
        client_id: string;
        contact_list_id: string;
        file: File | null;
    }>({
        client_id: '',
        contact_list_id: '',
        file: null,
    });

    const [newListName, setNewListName] = useState('');
    const [creatingList, setCreatingList] = useState(false);
    const [listError, setListError] = useState<string | null>(null);

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        form.post(urls.contacts.import, {
            forceFormData: true,
            onSuccess: () => form.reset(),
        });
    };

    /**
     * Create the audience list without leaving the upload screen, then preselect
     * it so the uploaded file lands in the list the client just named.
     */
    const createList = (): void => {
        const name = newListName.trim();

        if (name === '') {
            return;
        }

        setCreatingList(true);
        setListError(null);

        router.post(
            urls.contacts.storeList,
            { name },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: (page) => {
                    const lists = (page.props.contactLists ??
                        []) as ContactListOption[];
                    const created = lists.find((list) => list.name === name);

                    if (created) {
                        form.setData('contact_list_id', String(created.id));
                    }

                    setNewListName('');
                },
                onError: (errors) => {
                    setListError(
                        errors.name ?? 'That list could not be created.',
                    );
                },
                onFinish: () => setCreatingList(false),
            },
        );
    };

    return (
        <>
            <Head title="Import contacts" />

            <div className="flex flex-col gap-4">
                <PageHeader
                    icon={Upload}
                    title="Import contacts"
                    description="Upload a CSV file to create contacts, refresh the ones that already exist and block numbers that are on the DND list. Only the phone number is required."
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <a href={urls.contacts.importTemplate}>
                                    <Download />
                                    Download template
                                </a>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href={urls.contacts.index}>
                                    <ArrowLeft />
                                    Contacts
                                </Link>
                            </Button>
                        </>
                    }
                />

                <div className="grid gap-4 lg:grid-cols-5">
                    <Card className="lg:col-span-3">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-sm font-medium">
                                <FileUp className="size-4 text-brand" />
                                Upload a CSV
                            </CardTitle>
                            <CardDescription>
                                Rows are matched on the normalised phone number,
                                so re-importing the same file updates contacts
                                instead of duplicating them.
                            </CardDescription>
                        </CardHeader>

                        <CardContent>
                            <form
                                onSubmit={submit}
                                className="flex flex-col gap-4"
                            >
                                {clients.length > 0 && (
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="client_id">
                                            Client account
                                        </Label>
                                        <Select
                                            value={
                                                form.data.client_id || 'none'
                                            }
                                            onValueChange={(value) =>
                                                form.setData(
                                                    'client_id',
                                                    value === 'none'
                                                        ? ''
                                                        : value,
                                                )
                                            }
                                        >
                                            <SelectTrigger id="client_id">
                                                <SelectValue placeholder="Select a client" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="none">
                                                    Select a client
                                                </SelectItem>
                                                {clients.map((client) => (
                                                    <SelectItem
                                                        key={client.id}
                                                        value={String(
                                                            client.id,
                                                        )}
                                                    >
                                                        {client.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError
                                            message={form.errors.client_id}
                                        />
                                    </div>
                                )}

                                <div className="grid gap-1.5">
                                    <Label htmlFor="file">CSV file</Label>
                                    <Input
                                        id="file"
                                        type="file"
                                        accept=".csv,.txt,text/csv"
                                        onChange={(event) =>
                                            form.setData(
                                                'file',
                                                event.target.files?.[0] ?? null,
                                            )
                                        }
                                        required
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Comma separated, the first row is the
                                        header. Existing contacts are updated
                                        and rows without a valid phone number
                                        are skipped.
                                    </p>
                                    <InputError message={form.errors.file} />
                                </div>

                                <div className="grid gap-1.5">
                                    <Label htmlFor="contact_list_id">
                                        Add to list (optional)
                                    </Label>
                                    <Select
                                        value={
                                            form.data.contact_list_id || 'none'
                                        }
                                        onValueChange={(value) =>
                                            form.setData(
                                                'contact_list_id',
                                                value === 'none' ? '' : value,
                                            )
                                        }
                                    >
                                        <SelectTrigger id="contact_list_id">
                                            <SelectValue placeholder="Do not add to a list" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">
                                                Do not add to a list
                                            </SelectItem>
                                            {contactLists.map((list) => (
                                                <SelectItem
                                                    key={list.id}
                                                    value={String(list.id)}
                                                >
                                                    {list.name} ·{' '}
                                                    {list.contacts_count}{' '}
                                                    contacts
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        message={form.errors.contact_list_id}
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        A contact can belong to several lists.
                                        Numbers that already exist are added to
                                        this list without leaving their other
                                        lists, so overlapping audiences are only
                                        de-duplicated when a campaign is sent.
                                    </p>

                                    <div className="mt-1 grid gap-1.5 border-t border-border/60 pt-3">
                                        <Label htmlFor="new_list_name">
                                            Or create a list
                                        </Label>
                                        <div className="flex items-center gap-2">
                                            <Input
                                                id="new_list_name"
                                                value={newListName}
                                                onChange={(event) => {
                                                    setNewListName(
                                                        event.target.value,
                                                    );
                                                    setListError(null);
                                                }}
                                                onKeyDown={(event) => {
                                                    if (event.key === 'Enter') {
                                                        event.preventDefault();
                                                        createList();
                                                    }
                                                }}
                                                placeholder="Accra retail"
                                            />
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={createList}
                                                disabled={
                                                    creatingList ||
                                                    newListName.trim() === ''
                                                }
                                            >
                                                <Plus />
                                                {creatingList
                                                    ? 'Creating…'
                                                    : 'Create'}
                                            </Button>
                                        </div>
                                        {listError && (
                                            <p className="text-xs text-destructive">
                                                {listError}
                                            </p>
                                        )}
                                    </div>
                                </div>

                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    <Upload />
                                    {form.processing
                                        ? 'Importing…'
                                        : 'Import contacts'}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>

                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-sm font-medium">
                                <Users className="size-4 text-brand" />
                                Supported columns
                            </CardTitle>
                            <CardDescription>
                                Unknown columns are ignored, so an export can be
                                uploaded as-is.
                            </CardDescription>
                        </CardHeader>

                        <CardContent className="flex flex-col gap-3">
                            {columns.map((column) => (
                                <div key={column.name}>
                                    <p className="font-mono text-xs font-medium">
                                        {column.name}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {column.description}
                                    </p>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

ContactsImport.layout = {
    breadcrumbs: [
        {
            title: 'Contacts',
            href: urls.contacts.index,
        },
        {
            title: 'Import',
            href: urls.contacts.import,
        },
    ],
};
