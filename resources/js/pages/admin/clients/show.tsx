import { Form, Head, Link } from '@inertiajs/react';
import { useState, type MouseEvent } from 'react';
import { useConfirm, type ConfirmOptions } from '@/components/confirm-dialog';
import PageHeader from '@/components/page-header';
import StatusBadge from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
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
import { Textarea } from '@/components/ui/textarea';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import clientsRoute from '@/routes/admin/clients';
import adminUsersRoute from '@/routes/admin/users';

type Client = {
    id: number;
    name: string;
    account_type_label: string;
    code: string;
    email: string | null;
    phone: string | null;
    timezone: string;
    status: string;
    status_label: string;
    status_reason: string | null;
    credit_limit: number;
    notes: string | null;
    users_count: number;
    campaigns_count: number;
    contacts_count: number;
    registrations: {
        id: number;
        status_label: string;
        reviewer: string | null;
        reviewed_at: string | null;
    }[];
};
type Document = {
    id: number;
    type: string;
    type_label: string;
    title: string;
    status: string;
    status_label: string;
    original_name: string | null;
    mime_type: string | null;
    size: string;
    rejection_reason: string | null;
    uploaded_by: string | null;
    reviewed_by: string | null;
    reviewed_at: string | null;
    expires_at: string | null;
};
type Entitlement = {
    service: string;
    service_label: string;
    is_enabled: boolean;
    platform_enabled: boolean;
    monthly_quota: number | null;
    daily_quota: number | null;
    notes: string | null;
    quota_label: string;
};
type Props = {
    client: Client;
    documents: Document[];
    entitlements: Entitlement[];
    documentTypes: { value: string; label: string; is_required: boolean }[];
    missingDocuments: string[];
    can: {
        update: boolean;
        transition: boolean;
        approve: boolean;
        activate: boolean;
        transitions: string[];
    };
};

export default function ClientShow({
    client,
    documents,
    entitlements,
    documentTypes,
    missingDocuments,
    can,
}: Props) {
    const [previewDocument, setPreviewDocument] = useState<Document | null>(
        null,
    );
    const confirm = useConfirm();

    /**
     * Staff transitions live inside Inertia <Form>s, so the native submit is
     * cancelled and re-dispatched only once the shared dialog is accepted.
     * Invalid forms report their missing fields first.
     */
    const guardSubmit = (
        event: MouseEvent<HTMLButtonElement>,
        options: ConfirmOptions,
    ): void => {
        event.preventDefault();
        const form = event.currentTarget.form;

        if (form !== null && !form.reportValidity()) {
            return;
        }

        void confirm(options).then((confirmed) => {
            if (confirmed) {
                form?.requestSubmit();
            }
        });
    };

    return (
        <>
            <Head title={client.name} />
            <div className="grid gap-6">
                <PageHeader
                    title={client.name}
                    description={`${client.account_type_label} · ${client.code} · ${client.email ?? 'No email'} · ${client.phone ?? 'No phone'}`}
                    badge={
                        <StatusBadge
                            status={client.status}
                            label={client.status_label}
                        />
                    }
                    actions={
                        <>
                            <Button asChild variant="outline">
                                <Link
                                    href={adminUsersRoute.index.url({
                                        query: { client_id: client.id },
                                    })}
                                >
                                    Manage users ({client.users_count})
                                </Link>
                            </Button>
                            <Button asChild variant="outline">
                                <Link href={clientsRoute.index.url()}>
                                    All clients
                                </Link>
                            </Button>
                        </>
                    }
                />
                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <Metric label="Users" value={client.users_count} />
                    <Metric label="Campaigns" value={client.campaigns_count} />
                    <Metric label="Contacts" value={client.contacts_count} />
                    <Metric
                        label="Credit limit"
                        value={Number(client.credit_limit).toLocaleString(
                            undefined,
                            { style: 'currency', currency: 'GHS' },
                        )}
                    />
                </div>
                <Card>
                    <CardHeader>
                        <CardTitle>Organisation profile</CardTitle>
                        <CardDescription>
                            Update account details, billing limit and support
                            notes.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form
                            {...clientsRoute.update.form(client.id)}
                            className="grid gap-4 md:grid-cols-2"
                        >
                            {({ processing }) => (
                                <>
                                    <Field
                                        name="name"
                                        label="Organisation name"
                                        value={client.name}
                                    />
                                    <Field
                                        name="email"
                                        label="Contact email"
                                        value={client.email ?? ''}
                                        type="email"
                                    />
                                    <Field
                                        name="phone"
                                        label="Contact phone"
                                        value={client.phone ?? ''}
                                    />
                                    <Field
                                        name="timezone"
                                        label="Timezone"
                                        value={client.timezone}
                                    />
                                    <Field
                                        name="credit_limit"
                                        label="Credit limit (GHS)"
                                        value={client.credit_limit}
                                        type="number"
                                    />
                                    <div className="grid gap-2 md:col-span-2">
                                        <Label htmlFor="client-notes">
                                            Internal notes
                                        </Label>
                                        <Textarea
                                            id="client-notes"
                                            name="notes"
                                            defaultValue={client.notes ?? ''}
                                            rows={3}
                                        />
                                    </div>
                                    <Button
                                        className="w-fit"
                                        disabled={processing}
                                    >
                                        Save profile
                                    </Button>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Service entitlements</CardTitle>
                        <CardDescription>
                            Disabled services block campaign submission. Quotas
                            count recipients in the tenant timezone.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form
                            {...clientsRoute.entitlements.update.form(
                                client.id,
                            )}
                            className="grid gap-4"
                        >
                            {({ processing }) => (
                                <>
                                    {entitlements.map((item, index) => (
                                        <div
                                            key={item.service}
                                            className="grid gap-3 rounded-lg border p-4 sm:grid-cols-[1fr_auto_1fr_1fr] sm:items-end"
                                        >
                                            <input
                                                type="hidden"
                                                name={`entitlements[${index}][service]`}
                                                value={item.service}
                                            />
                                            <div>
                                                <p className="flex flex-wrap items-center gap-2 font-medium">
                                                    {item.service_label}
                                                    {!item.platform_enabled && (
                                                        <Badge variant="destructive">
                                                            Off platform-wide
                                                        </Badge>
                                                    )}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    Current: {item.quota_label}
                                                </p>
                                            </div>
                                            <label className="flex items-center gap-2 text-sm">
                                                <input
                                                    type="hidden"
                                                    name={`entitlements[${index}][is_enabled]`}
                                                    value="0"
                                                />
                                                <input
                                                    type="checkbox"
                                                    name={`entitlements[${index}][is_enabled]`}
                                                    value="1"
                                                    defaultChecked={
                                                        item.is_enabled
                                                    }
                                                />{' '}
                                                Enabled
                                            </label>
                                            <div className="grid gap-2">
                                                <Label>
                                                    Monthly recipients
                                                </Label>
                                                <Input
                                                    name={`entitlements[${index}][monthly_quota]`}
                                                    type="number"
                                                    min="0"
                                                    defaultValue={
                                                        item.monthly_quota ?? ''
                                                    }
                                                    placeholder="Unlimited"
                                                />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label>Daily recipients</Label>
                                                <Input
                                                    name={`entitlements[${index}][daily_quota]`}
                                                    type="number"
                                                    min="0"
                                                    defaultValue={
                                                        item.daily_quota ?? ''
                                                    }
                                                    placeholder="Unlimited"
                                                />
                                            </div>
                                            <div className="grid gap-2 sm:col-span-4">
                                                <Label>Notes</Label>
                                                <Input
                                                    name={`entitlements[${index}][notes]`}
                                                    defaultValue={
                                                        item.notes ?? ''
                                                    }
                                                />
                                            </div>
                                        </div>
                                    ))}
                                    <Button
                                        className="w-fit"
                                        disabled={processing}
                                    >
                                        Save entitlements
                                    </Button>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Compliance documents</CardTitle>
                        <CardDescription>
                            {missingDocuments.length
                                ? `One acceptable identification document must be verified before activation. Accepted: ${missingDocuments.join(', ')}`
                                : 'An acceptable identification document is verified.'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-5">
                        <Form
                            {...clientsRoute.documents.store.form(client.id)}
                            encType="multipart/form-data"
                            className="grid gap-3 rounded-lg border p-4 sm:grid-cols-4 sm:items-end"
                        >
                            {({ processing }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label>Document type</Label>
                                        <select
                                            name="type"
                                            required
                                            className="h-9 rounded-md border bg-background px-3 text-sm"
                                        >
                                            {documentTypes.map((type) => (
                                                <option
                                                    value={type.value}
                                                    key={type.value}
                                                >
                                                    {type.label}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>Title</Label>
                                        <Input name="title" required />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>File (PDF/JPG/PNG)</Label>
                                        <Input
                                            name="document"
                                            type="file"
                                            required
                                            accept=".pdf,.jpg,.jpeg,.png"
                                        />
                                    </div>
                                    <Button disabled={processing}>
                                        Upload
                                    </Button>
                                </>
                            )}
                        </Form>
                        <div className="grid gap-3">
                            {documents.map((document) => (
                                <div
                                    key={document.id}
                                    className="flex flex-col gap-3 rounded-lg border p-4 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div>
                                        <p className="font-medium">
                                            {document.title}{' '}
                                            <StatusBadge
                                                status={document.status}
                                                label={document.status_label}
                                            />
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {document.type_label} ·{' '}
                                            {document.original_name ?? 'file'} ·{' '}
                                            {document.size} · Uploaded by{' '}
                                            {document.uploaded_by ?? '—'}
                                        </p>
                                        {document.rejection_reason && (
                                            <p className="text-sm text-destructive">
                                                {document.rejection_reason}
                                            </p>
                                        )}
                                    </div>
                                    <div className="flex flex-wrap gap-2">
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() =>
                                                setPreviewDocument(document)
                                            }
                                        >
                                            View file
                                        </Button>
                                        {document.status !== 'verified' && (
                                            <Form
                                                {...clientsRoute.documents.review.form(
                                                    [client.id, document.id],
                                                )}
                                            >
                                                {({ processing }) => (
                                                    <>
                                                        <input
                                                            type="hidden"
                                                            name="decision"
                                                            value="verify"
                                                        />
                                                        <Button
                                                            size="sm"
                                                            disabled={
                                                                processing
                                                            }
                                                        >
                                                            Verify
                                                        </Button>
                                                    </>
                                                )}
                                            </Form>
                                        )}
                                        {document.status !== 'verified' && (
                                            <Form
                                                {...clientsRoute.documents.review.form(
                                                    [client.id, document.id],
                                                )}
                                            >
                                                {({ processing }) => (
                                                    <>
                                                        <input
                                                            type="hidden"
                                                            name="decision"
                                                            value="reject"
                                                        />
                                                        <Input
                                                            name="reason"
                                                            aria-label="Rejection reason"
                                                            placeholder="Rejection reason"
                                                            required
                                                            minLength={5}
                                                        />
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            disabled={
                                                                processing
                                                            }
                                                            onClick={(event) =>
                                                                guardSubmit(
                                                                    event,
                                                                    {
                                                                        title: `Reject ${document.title}?`,
                                                                        description:
                                                                            'The client sees the rejection reason and has to upload a replacement before the account can be activated.',
                                                                        confirmLabel:
                                                                            'Reject document',
                                                                        tone: 'danger',
                                                                    },
                                                                )
                                                            }
                                                        >
                                                            Reject
                                                        </Button>
                                                    </>
                                                )}
                                            </Form>
                                        )}
                                        <Form
                                            {...clientsRoute.documents.destroy.form(
                                                [client.id, document.id],
                                            )}
                                        >
                                            {({ processing }) => (
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    disabled={processing}
                                                    onClick={(event) =>
                                                        guardSubmit(event, {
                                                            title: `Delete ${document.title}?`,
                                                            description:
                                                                'The file is removed from the compliance record and cannot be restored. Upload a new copy if it is still required.',
                                                            confirmLabel:
                                                                'Delete document',
                                                            tone: 'danger',
                                                        })
                                                    }
                                                >
                                                    Delete
                                                </Button>
                                            )}
                                        </Form>
                                    </div>
                                </div>
                            ))}
                            {documents.length === 0 && (
                                <p className="text-sm text-muted-foreground">
                                    No documents uploaded.
                                </p>
                            )}
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Account lifecycle</CardTitle>
                        <CardDescription>
                            {client.status_reason ??
                                'Status transitions are recorded with the acting staff member and reason.'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-wrap gap-3">
                        {can.activate && (
                            <Action
                                action={clientsRoute.activate.form(client.id)}
                                label="Activate client"
                            />
                        )}
                        {can.transitions.includes('suspended') && (
                            <Form
                                {...clientsRoute.suspend.form(client.id)}
                                className="flex gap-2"
                            >
                                <Input
                                    name="reason"
                                    required
                                    minLength={5}
                                    placeholder="Suspension reason"
                                />
                                <Button
                                    variant="outline"
                                    onClick={(event) =>
                                        guardSubmit(event, {
                                            title: `Suspend ${client.name}?`,
                                            description:
                                                'The client cannot sign in or submit campaigns until staff reactivate the account.',
                                            confirmLabel: 'Suspend client',
                                            tone: 'danger',
                                        })
                                    }
                                >
                                    Suspend
                                </Button>
                            </Form>
                        )}
                        {can.transitions.includes('active') &&
                            client.status === 'suspended' && (
                                <Action
                                    action={clientsRoute.reactivate.form(
                                        client.id,
                                    )}
                                    label="Reactivate"
                                />
                            )}
                        {can.transitions.includes('archived') && (
                            <Form
                                {...clientsRoute.archive.form(client.id)}
                                className="flex gap-2"
                            >
                                <Input
                                    name="reason"
                                    required
                                    minLength={5}
                                    placeholder="Archive reason"
                                />
                                <Button
                                    variant="destructive"
                                    onClick={(event) =>
                                        guardSubmit(event, {
                                            title: `Archive ${client.name}?`,
                                            description:
                                                'The account is closed for campaigns and sign-in. Everything stays available to staff for audit.',
                                            confirmLabel: 'Archive client',
                                            tone: 'danger',
                                        })
                                    }
                                >
                                    Archive
                                </Button>
                            </Form>
                        )}
                    </CardContent>
                </Card>
                <Dialog
                    open={previewDocument !== null}
                    onOpenChange={(open) => {
                        if (!open) setPreviewDocument(null);
                    }}
                >
                    <DialogContent className="flex h-[90vh] w-[calc(100%-2rem)] max-w-6xl flex-col overflow-hidden p-4 sm:p-6">
                        <DialogHeader>
                            <DialogTitle>
                                {previewDocument?.title ?? 'Document preview'}
                            </DialogTitle>
                            <DialogDescription>
                                {previewDocument?.original_name ??
                                    'Private compliance document'}{' '}
                                · {previewDocument?.size}
                            </DialogDescription>
                        </DialogHeader>
                        {previewDocument && (
                            <div className="min-h-0 flex-1 overflow-hidden rounded-md border bg-muted/20">
                                {previewDocument.mime_type?.startsWith(
                                    'image/',
                                ) ? (
                                    <img
                                        src={clientsRoute.documents.view.url([
                                            client.id,
                                            previewDocument.id,
                                        ])}
                                        alt={previewDocument.title}
                                        className="h-full w-full object-contain"
                                    />
                                ) : (
                                    <iframe
                                        src={clientsRoute.documents.view.url([
                                            client.id,
                                            previewDocument.id,
                                        ])}
                                        title={previewDocument.title}
                                        className="h-full min-h-[50vh] w-full"
                                    />
                                )}
                            </div>
                        )}
                    </DialogContent>
                </Dialog>
            </div>
        </>
    );
}

function Field({
    name,
    label,
    value,
    type = 'text',
}: {
    name: string;
    label: string;
    value: string | number;
    type?: string;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={name}>{label}</Label>
            <Input
                id={name}
                name={name}
                type={type}
                defaultValue={value}
                required={
                    name === 'name' ||
                    name === 'timezone' ||
                    name === 'credit_limit'
                }
            />
        </div>
    );
}
function Metric({ label, value }: { label: string; value: string | number }) {
    return (
        <div className="rounded-lg border p-4">
            <p className="text-sm text-muted-foreground">{label}</p>
            <p className="mt-1 text-xl font-semibold">{value}</p>
        </div>
    );
}
function Action({
    action,
    label,
}: {
    action: ReturnType<typeof clientsRoute.activate.form>;
    label: string;
}) {
    return (
        <Form {...action}>
            {({ processing }) => <Button disabled={processing}>{label}</Button>}
        </Form>
    );
}

ClientShow.layout = {
    breadcrumbs: [{ title: 'Clients', href: clientsRoute.index.url() }],
};
