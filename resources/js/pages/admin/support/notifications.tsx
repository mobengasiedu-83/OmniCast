import { Head, useForm } from '@inertiajs/react';
import { BellRing, MapPin } from 'lucide-react';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { update } from '@/routes/admin/support/notifications';

type Settings = {
    contact_email: string;
    support_email: string;
    sales_email: string;
    phone: string;
    whatsapp: string;
    office_address: string;
    opening_hours: string;
    response_time: string;
    status_url: string;
    acknowledge_enquiries: boolean;
    notify_on_status_change: boolean;
    notify_team: boolean;
};

type PageProps = { settings: Settings };

/**
 * What the support desk publishes, and what it sends.
 *
 * The contact fields are the only place a phone number, WhatsApp line or support
 * hours become public: a blank field is simply not rendered on the contact page, so
 * nothing reaches a visitor until the business has confirmed it. The three
 * switches decide whether the requester is acknowledged, whether they hear about a
 * status change, and whether the owning team is emailed.
 */
export default function SupportNotifications({ settings }: PageProps) {
    const form = useForm<Settings>({ ...settings });

    const save = (): void => {
        form.put(update.url(), { preserveScroll: true });
    };

    return (
        <>
            <Head title="Support notifications" />

            <div className="grid gap-6">
                <PageHeader
                    icon={BellRing}
                    title="Support notifications"
                    description="The details the contact page publishes, and the emails the desk sends."
                />

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-sm">
                                <MapPin className="size-4 text-brand" />
                                Published contact details
                            </CardTitle>
                            <p className="text-xs text-muted-foreground">
                                A blank field is hidden on the public contact
                                page.
                            </p>
                        </CardHeader>

                        <CardContent className="grid gap-3">
                            <Field
                                id="support_email"
                                label="Support inbox"
                                type="email"
                                value={form.data.support_email}
                                error={form.errors.support_email}
                                onChange={(value) =>
                                    form.setData('support_email', value)
                                }
                            />
                            <Field
                                id="sales_email"
                                label="Sales inbox"
                                type="email"
                                value={form.data.sales_email}
                                error={form.errors.sales_email}
                                onChange={(value) =>
                                    form.setData('sales_email', value)
                                }
                            />
                            <Field
                                id="contact_email"
                                label="General contact address"
                                type="email"
                                value={form.data.contact_email}
                                error={form.errors.contact_email}
                                onChange={(value) =>
                                    form.setData('contact_email', value)
                                }
                            />
                            <Field
                                id="phone"
                                label="Telephone"
                                value={form.data.phone}
                                error={form.errors.phone}
                                onChange={(value) =>
                                    form.setData('phone', value)
                                }
                            />
                            <Field
                                id="whatsapp"
                                label="WhatsApp number"
                                value={form.data.whatsapp}
                                error={form.errors.whatsapp}
                                onChange={(value) =>
                                    form.setData('whatsapp', value)
                                }
                            />
                            <Field
                                id="office_address"
                                label="Office address"
                                value={form.data.office_address}
                                error={form.errors.office_address}
                                onChange={(value) =>
                                    form.setData('office_address', value)
                                }
                            />
                            <Field
                                id="opening_hours"
                                label="Support hours"
                                value={form.data.opening_hours}
                                error={form.errors.opening_hours}
                                placeholder="Mon–Fri, 08:00–17:00 (GMT)"
                                onChange={(value) =>
                                    form.setData('opening_hours', value)
                                }
                            />
                            <Field
                                id="response_time"
                                label="Response-time promise"
                                value={form.data.response_time}
                                error={form.errors.response_time}
                                placeholder="We reply within one business day."
                                onChange={(value) =>
                                    form.setData('response_time', value)
                                }
                            />
                            <Field
                                id="status_url"
                                label="Service status page"
                                type="url"
                                value={form.data.status_url}
                                error={form.errors.status_url}
                                placeholder="https://status.example.com"
                                onChange={(value) =>
                                    form.setData('status_url', value)
                                }
                            />
                            <Button
                                type="button"
                                onClick={save}
                                disabled={form.processing}
                                className="mt-1 w-fit"
                            >
                                Save settings
                            </Button>
                        </CardContent>
                    </Card>

                    <Card className="h-fit">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-sm">
                                <BellRing className="size-4 text-brand" />
                                Emails the desk sends
                            </CardTitle>
                        </CardHeader>

                        <CardContent className="grid gap-4">
                            <Toggle
                                id="acknowledge_enquiries"
                                label="Acknowledge every conversation"
                                description="The requester receives the reference number and the response-time promise straight away."
                                checked={form.data.acknowledge_enquiries}
                                onChange={(value) =>
                                    form.setData('acknowledge_enquiries', value)
                                }
                            />
                            <Toggle
                                id="notify_on_status_change"
                                label="Email status changes"
                                description="The requester hears when a conversation is resolved or closed."
                                checked={form.data.notify_on_status_change}
                                onChange={(value) =>
                                    form.setData(
                                        'notify_on_status_change',
                                        value,
                                    )
                                }
                            />
                            <Toggle
                                id="notify_team"
                                label="Alert the owning team"
                                description="A new conversation, and every requester reply, is emailed to the team's routing address."
                                checked={form.data.notify_team}
                                onChange={(value) =>
                                    form.setData('notify_team', value)
                                }
                            />

                            <Button
                                type="button"
                                onClick={save}
                                disabled={form.processing}
                                className="w-fit"
                            >
                                Save settings
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

/**
 * A labelled text field in the settings panel.
 */
function Field({
    id,
    label,
    value,
    error,
    type = 'text',
    placeholder,
    onChange,
}: {
    id: string;
    label: string;
    value: string;
    error?: string;
    type?: string;
    placeholder?: string;
    onChange: (value: string) => void;
}) {
    return (
        <div className="grid gap-1">
            <Label htmlFor={id}>{label}</Label>
            <Input
                id={id}
                type={type}
                value={value}
                placeholder={placeholder}
                onChange={(event) => onChange(event.target.value)}
            />
            {error && <p className="text-xs text-destructive">{error}</p>}
        </div>
    );
}

/**
 * A switch with an explanation of the email behind it.
 */
function Toggle({
    id,
    label,
    description,
    checked,
    onChange,
}: {
    id: string;
    label: string;
    description: string;
    checked: boolean;
    onChange: (value: boolean) => void;
}) {
    return (
        <label
            htmlFor={id}
            className="flex items-start gap-3 rounded-lg border border-border/70 p-3"
        >
            <input
                id={id}
                type="checkbox"
                checked={checked}
                onChange={(event) => onChange(event.target.checked)}
                className="mt-0.5 size-4 rounded border-input"
            />
            <span>
                <span className="block text-sm font-medium">{label}</span>
                <span className="block text-xs text-muted-foreground">
                    {description}
                </span>
            </span>
        </label>
    );
}

