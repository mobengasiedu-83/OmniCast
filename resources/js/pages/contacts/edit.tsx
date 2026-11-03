import { Head } from '@inertiajs/react';
import { UserRoundPen } from 'lucide-react';
import ContactForm from '@/components/contact-form';
import PageHeader from '@/components/page-header';
import { urls } from '@/lib/urls';
import type {
    ClientOption,
    ContactFormValues,
    ContactListOption,
    SelectOption,
} from '@/types';

type EditableContact = {
    id: number;
    first_name: string;
    last_name: string | null;
    phone: string;
    alternative_phone: string | null;
    email: string | null;
    company: string | null;
    account_number: string | null;
    customer_id: string | null;
    location: string | null;
    language: string | null;
    timezone: string | null;
    consent_status: string;
    is_do_not_contact: boolean;
    contact_list_ids: number[];
};

type PageProps = {
    contactLists: ContactListOption[];
    consentStatuses: SelectOption[];
    clients: ClientOption[];
    contact: EditableContact;
};

/**
 * Normalises the persisted contact into the string based form shape.
 */
function initialValues(contact: EditableContact): Partial<ContactFormValues> {
    const text = (value: string | null): string => value ?? '';

    return {
        first_name: contact.first_name,
        last_name: text(contact.last_name),
        phone: contact.phone,
        alternative_phone: text(contact.alternative_phone),
        email: text(contact.email),
        company: text(contact.company),
        account_number: text(contact.account_number),
        customer_id: text(contact.customer_id),
        location: text(contact.location),
        language: text(contact.language),
        timezone: text(contact.timezone),
        consent_status: contact.consent_status,
        is_do_not_contact: contact.is_do_not_contact,
        contact_list_ids: contact.contact_list_ids,
    };
}

export default function ContactsEdit({ contact, ...options }: PageProps) {
    return (
        <>
            <Head title={`Edit ${contact.first_name}`} />

            <div className="flex flex-col gap-4">
                <PageHeader
                    icon={UserRoundPen}
                    title="Edit contact"
                    description={`${contact.phone} · updates apply to future campaigns.`}
                />

                <ContactForm
                    action={urls.contacts.update(contact.id)}
                    method="put"
                    contactLists={options.contactLists}
                    consentStatuses={options.consentStatuses}
                    clients={options.clients}
                    initial={initialValues(contact)}
                    submitLabel="Save changes"
                    cancelHref={urls.contacts.index}
                />
            </div>
        </>
    );
}

ContactsEdit.layout = {
    breadcrumbs: [
        {
            title: 'Contacts',
            href: urls.contacts.index,
        },
        {
            title: 'Edit contact',
            href: urls.contacts.index,
        },
    ],
};
