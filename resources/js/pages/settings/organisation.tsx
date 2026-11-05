import { Form, Head } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type DocumentRow = {
    id: number;
    type: string;
    title: string;
    status: string;
    status_label: string;
    rejection_reason: string | null;
    created_at: string | null;
};
type Props = {
    settings: Settings;
    timezones: string[];
    locales: string[];
    organisation: {
        name: string;
        account_type_label: string;
        email: string | null;
        phone: string | null;
        status: string;
        status_label: string;
        status_reason: string | null;
        registration_status: string | null;
        missing_documents: string[];
    };
    documents: DocumentRow[];
    documentTypes: { value: string; label: string; is_required: boolean }[];
    entitlements: { service: string; is_enabled: boolean; quota: string }[];
};
import { update as updateOrganisation } from '@/routes/organisation-settings';
import organisationDocuments from '@/routes/organisation-settings/documents';

type Settings = {
    timezone: string;
    locale?: string;
    branding?: { name?: string; primary_color?: string };
    notification_preferences?: { email?: boolean; sms?: boolean };
};

export default function OrganisationSettings({
    settings,
    timezones,
    locales,
    organisation,
    documents,
    documentTypes,
    entitlements,
}: Props) {
    return (
        <>
            <Head title="Organisation settings" />
            <div className="grid gap-6">
                <div>
                    <h1 className="text-2xl font-semibold">
                        Organisation settings
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {organisation.name} · {organisation.account_type_label}{' '}
                        · {organisation.email ?? 'No email'} ·{' '}
                        {organisation.phone ?? 'No phone'}
                    </p>
                    <p className="mt-1 text-sm">
                        Account status:{' '}
                        <strong>{organisation.status_label}</strong>
                        {organisation.registration_status
                            ? ` · application ${organisation.registration_status}`
                            : ''}
                    </p>
                    {organisation.status_reason && (
                        <p className="mt-1 text-sm text-destructive">
                            {organisation.status_reason}
                        </p>
                    )}
                </div>
                <section className="grid gap-3 rounded-lg border p-4">
                    <div>
                        <h2 className="font-semibold">Compliance documents</h2>
                        <p className="text-sm text-muted-foreground">
                            {organisation.status === 'active'
                                ? 'Compliance documents on file.'
                                : `Submit one identification document for review. Accepted: ${organisation.missing_documents.join(', ') || 'All required documents submitted.'}`}
                        </p>
                    </div>
                    <Form
                        {...organisationDocuments.store.form()}
                        encType="multipart/form-data"
                        className="grid gap-3 sm:grid-cols-4 sm:items-end"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="document-type">
                                        Document type
                                    </Label>
                                    <select
                                        id="document-type"
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
                                                {type.is_required ? ' *' : ''}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="document-title">
                                        Document title
                                    </Label>
                                    <Input
                                        id="document-title"
                                        name="title"
                                        required
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="document-file">
                                        File (PDF/JPG/PNG)
                                    </Label>
                                    <Input
                                        id="document-file"
                                        name="document"
                                        type="file"
                                        required
                                        accept=".pdf,.jpg,.jpeg,.png"
                                    />
                                    {errors.document && (
                                        <p className="text-sm text-destructive">
                                            {errors.document}
                                        </p>
                                    )}
                                </div>
                                <Button disabled={processing}>
                                    {processing
                                        ? 'Uploading…'
                                        : 'Submit for review'}
                                </Button>
                            </>
                        )}
                    </Form>
                    <div className="grid gap-2">
                        {documents.map((document) => (
                            <div
                                key={document.id}
                                className="flex flex-wrap items-center justify-between gap-2 rounded-md bg-muted/40 px-3 py-2 text-sm"
                            >
                                <span>{document.title}</span>
                                <span>
                                    {document.status_label}
                                    {document.rejection_reason
                                        ? ` · ${document.rejection_reason}`
                                        : ''}
                                </span>
                            </div>
                        ))}
                        {documents.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                No documents submitted yet.
                            </p>
                        )}
                    </div>
                </section>
                <section className="grid gap-3 rounded-lg border p-4">
                    <h2 className="font-semibold">Service status</h2>
                    <div className="grid gap-2 sm:grid-cols-3">
                        {entitlements.map((item) => (
                            <div
                                key={item.service}
                                className="rounded-md bg-muted/40 p-3"
                            >
                                <p className="font-medium">{item.service}</p>
                                <p className="text-sm text-muted-foreground">
                                    {item.is_enabled ? 'Enabled' : 'Disabled'} ·{' '}
                                    {item.quota}
                                </p>
                            </div>
                        ))}
                        {entitlements.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                Services are assigned during account review.
                            </p>
                        )}
                    </div>
                </section>
                <Form
                    {...updateOrganisation.form()}
                    className="mt-6 grid max-w-2xl gap-5"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="organisation-name">
                                    Organisation name
                                </Label>
                                <Input
                                    id="organisation-name"
                                    name="name"
                                    defaultValue={organisation.name}
                                    required
                                />
                                {errors.name && (
                                    <p className="text-sm text-destructive">
                                        {errors.name}
                                    </p>
                                )}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="organisation-email">
                                    Contact email
                                </Label>
                                <Input
                                    id="organisation-email"
                                    name="email"
                                    type="email"
                                    defaultValue={organisation.email ?? ''}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="organisation-phone">
                                    Contact phone
                                </Label>
                                <Input
                                    id="organisation-phone"
                                    name="phone"
                                    defaultValue={organisation.phone ?? ''}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="timezone">Timezone</Label>
                                <select
                                    id="timezone"
                                    name="timezone"
                                    defaultValue={settings.timezone}
                                    className="rounded-md border bg-background px-3 py-2"
                                >
                                    {timezones.map((timezone) => (
                                        <option key={timezone}>
                                            {timezone}
                                        </option>
                                    ))}
                                </select>
                                {errors.timezone && (
                                    <p className="text-sm text-destructive">
                                        {errors.timezone}
                                    </p>
                                )}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="locale">Locale</Label>
                                <select
                                    id="locale"
                                    name="locale"
                                    defaultValue={settings.locale ?? 'en'}
                                    className="rounded-md border bg-background px-3 py-2"
                                >
                                    {locales.map((locale) => (
                                        <option key={locale} value={locale}>
                                            {new Intl.DisplayNames([locale], {
                                                type: 'language',
                                            }).of(locale) ?? locale}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="branding-name">
                                    Brand display name
                                </Label>
                                <Input
                                    id="branding-name"
                                    name="branding[name]"
                                    defaultValue={settings.branding?.name}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="branding-color">
                                    Brand primary color
                                </Label>
                                <Input
                                    id="branding-color"
                                    name="branding[primary_color]"
                                    defaultValue={
                                        settings.branding?.primary_color
                                    }
                                    placeholder="#123456"
                                />
                            </div>
                            <label className="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    name="notification_preferences[email]"
                                    value="1"
                                    defaultChecked={
                                        settings.notification_preferences
                                            ?.email ?? true
                                    }
                                />
                                Email notifications
                            </label>
                            <label className="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    name="notification_preferences[sms]"
                                    value="1"
                                    defaultChecked={
                                        settings.notification_preferences
                                            ?.sms ?? false
                                    }
                                />
                                SMS notifications
                            </label>
                            <Button disabled={processing}>
                                {processing ? 'Saving…' : 'Save preferences'}
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
