import { Link, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type {
    ClientOption,
    ContactFormValues,
    ContactListOption,
    SelectOption,
} from '@/types';

const defaults: ContactFormValues = {
    first_name: '',
    last_name: '',
    phone: '',
    alternative_phone: '',
    email: '',
    company: '',
    account_number: '',
    customer_id: '',
    location: '',
    language: 'en',
    timezone: 'Africa/Accra',
    consent_status: 'pending',
    is_do_not_contact: false,
    contact_list_ids: [],
    client_id: '',
};

export default function ContactForm({
    action,
    method,
    contactLists,
    consentStatuses,
    clients,
    initial,
    submitLabel,
    cancelHref,
}: {
    action: string;
    method: 'post' | 'put';
    contactLists: ContactListOption[];
    consentStatuses: SelectOption[];
    clients: ClientOption[];
    initial?: Partial<ContactFormValues>;
    submitLabel: string;
    cancelHref: string;
}) {
    const form = useForm<ContactFormValues>({ ...defaults, ...initial });

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        if (method === 'put') {
            form.put(action);

            return;
        }

        form.post(action);
    };

    const toggleList = (listId: number): void => {
        const selected = form.data.contact_list_ids.includes(listId);

        form.setData(
            'contact_list_ids',
            selected
                ? form.data.contact_list_ids.filter((id) => id !== listId)
                : [...form.data.contact_list_ids, listId],
        );
    };

    return (
        <form onSubmit={submit} className="flex flex-col gap-4">
            <Card className="py-4">
                <CardHeader>
                    <CardTitle>Contact details</CardTitle>
                    <CardDescription>
                        A phone number and consent status are all that is
                        required to reach someone.
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-3 sm:grid-cols-2">
                    {clients.length > 0 && (
                        <div className="grid gap-1.5 sm:col-span-2">
                            <Label htmlFor="client_id">Client account</Label>
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
                            <InputError message={form.errors.client_id} />
                        </div>
                    )}

                    <div className="grid gap-1.5">
                        <Label htmlFor="first_name">First name</Label>
                        <Input
                            id="first_name"
                            value={form.data.first_name}
                            onChange={(event) =>
                                form.setData('first_name', event.target.value)
                            }
                            required
                        />
                        <InputError message={form.errors.first_name} />
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor="last_name">Last name</Label>
                        <Input
                            id="last_name"
                            value={form.data.last_name}
                            onChange={(event) =>
                                form.setData('last_name', event.target.value)
                            }
                        />
                        <InputError message={form.errors.last_name} />
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor="phone">Phone number</Label>
                        <Input
                            id="phone"
                            value={form.data.phone}
                            onChange={(event) =>
                                form.setData('phone', event.target.value)
                            }
                            placeholder="+233201234567"
                            required
                        />
                        <InputError message={form.errors.phone} />
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor="alternative_phone">
                            Alternative phone
                        </Label>
                        <Input
                            id="alternative_phone"
                            value={form.data.alternative_phone}
                            onChange={(event) =>
                                form.setData(
                                    'alternative_phone',
                                    event.target.value,
                                )
                            }
                        />
                        <InputError message={form.errors.alternative_phone} />
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor="email">Email</Label>
                        <Input
                            id="email"
                            type="email"
                            value={form.data.email}
                            onChange={(event) =>
                                form.setData('email', event.target.value)
                            }
                        />
                        <InputError message={form.errors.email} />
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor="company">Company</Label>
                        <Input
                            id="company"
                            value={form.data.company}
                            onChange={(event) =>
                                form.setData('company', event.target.value)
                            }
                        />
                        <InputError message={form.errors.company} />
                    </div>
                </CardContent>
            </Card>

            <Card className="py-4">
                <CardHeader>
                    <CardTitle>Account &amp; reachability</CardTitle>
                    <CardDescription>
                        Optional metadata used for personalising message copy.
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-3 sm:grid-cols-2">
                    <div className="grid gap-1.5">
                        <Label htmlFor="account_number">Account number</Label>
                        <Input
                            id="account_number"
                            value={form.data.account_number}
                            onChange={(event) =>
                                form.setData(
                                    'account_number',
                                    event.target.value,
                                )
                            }
                        />
                        <InputError message={form.errors.account_number} />
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor="customer_id">Customer ID</Label>
                        <Input
                            id="customer_id"
                            value={form.data.customer_id}
                            onChange={(event) =>
                                form.setData('customer_id', event.target.value)
                            }
                        />
                        <InputError message={form.errors.customer_id} />
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor="location">Location</Label>
                        <Input
                            id="location"
                            value={form.data.location}
                            onChange={(event) =>
                                form.setData('location', event.target.value)
                            }
                        />
                        <InputError message={form.errors.location} />
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor="language">Language</Label>
                        <Input
                            id="language"
                            value={form.data.language}
                            onChange={(event) =>
                                form.setData('language', event.target.value)
                            }
                            placeholder="en"
                        />
                        <InputError message={form.errors.language} />
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor="timezone">Timezone</Label>
                        <Input
                            id="timezone"
                            value={form.data.timezone}
                            onChange={(event) =>
                                form.setData('timezone', event.target.value)
                            }
                            placeholder="Africa/Accra"
                        />
                        <InputError message={form.errors.timezone} />
                    </div>
                </CardContent>
            </Card>
            <Card className="py-4">
                <CardHeader>
                    <CardTitle>Consent &amp; audiences</CardTitle>
                    <CardDescription>
                        Campaigns only reach contacts that have opted in.
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-4">
                    <div className="grid gap-1.5 sm:max-w-xs">
                        <Label htmlFor="consent_status">Consent status</Label>
                        <Select
                            value={form.data.consent_status}
                            onValueChange={(value) =>
                                form.setData('consent_status', value)
                            }
                        >
                            <SelectTrigger id="consent_status">
                                <SelectValue placeholder="Select consent" />
                            </SelectTrigger>
                            <SelectContent>
                                {consentStatuses.map((status) => (
                                    <SelectItem
                                        key={status.value}
                                        value={status.value}
                                    >
                                        {status.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.consent_status} />
                    </div>

                    <div className="grid gap-1">
                        <label className="flex items-start gap-2 text-sm">
                            <Checkbox
                                checked={form.data.is_do_not_contact}
                                onCheckedChange={(checked) =>
                                    form.setData(
                                        'is_do_not_contact',
                                        checked === true,
                                    )
                                }
                            />
                            <span>
                                <span className="font-medium">
                                    Do not contact
                                </span>
                                <span className="text-muted-foreground block text-xs">
                                    Excludes this contact from every campaign.
                                </span>
                            </span>
                        </label>
                        <InputError message={form.errors.is_do_not_contact} />
                    </div>

                    <div className="grid gap-2">
                        <Label>Contact lists</Label>
                        {contactLists.length === 0 ? (
                            <p className="text-muted-foreground text-sm">
                                No contact lists have been created yet.
                            </p>
                        ) : (
                            <div className="grid gap-2 sm:grid-cols-2">
                                {contactLists.map((list) => (
                                    <label
                                        key={list.id}
                                        className="flex items-center gap-2 text-sm"
                                    >
                                        <Checkbox
                                            checked={form.data.contact_list_ids.includes(
                                                list.id,
                                            )}
                                            onCheckedChange={() =>
                                                toggleList(list.id)
                                            }
                                        />
                                        <span>{list.name}</span>
                                        <span className="text-muted-foreground text-xs">
                                            {list.contacts_count}
                                        </span>
                                    </label>
                                ))}
                            </div>
                        )}
                        <InputError message={form.errors.contact_list_ids} />
                    </div>
                </CardContent>
            </Card>

            <div className="flex items-center justify-end gap-2">
                <Button variant="outline" asChild>
                    <Link href={cancelHref}>Cancel</Link>
                </Button>
                <Button type="submit" disabled={form.processing}>
                    {form.processing ? 'Saving…' : submitLabel}
                </Button>
            </div>
        </form>
    );
}
