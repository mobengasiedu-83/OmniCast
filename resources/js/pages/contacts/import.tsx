import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Download, FileUp, Upload, Users } from 'lucide-react';
import { type FormEvent } from 'react';
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
        description: 'Optional. Falls back to a "name" column, then to the MSISDN.',
    },
    { name: 'last_name', description: 'Optional surname.' },
    {
        name: 'email',
        description: 'Optional. Stored lower-cased and used for duplicate detection.',
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

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        form.post(urls.contacts.import, {
            forceFormData: true,
            onSuccess: () => form.reset(),
        });
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
                                <FileUp className="text-brand size-4" />
                                Upload a CSV
                            </CardTitle>
                            <CardDescription>
                                Rows are matched on the normalised phone
                                number, so re-importing the same file updates
                                contacts instead of duplicating them.
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
                                            value={form.data.client_id || 'none'}
                                            onValueChange={(value) =>
                                                form.setData(
                                                    'client_id',
                                                    value === 'none' ? '' : value,
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
                                                        value={String(client.id)}
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
                                    <p className="text-muted-foreground text-xs">
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
                                </div>

                                <Button type="submit" disabled={form.processing}>
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
                                <Users className="text-brand size-4" />
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
                                    <p className="text-muted-foreground text-xs">
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

