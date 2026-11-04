import { Head } from '@inertiajs/react';
import { UserRoundPen } from 'lucide-react';
import ContactForm from '@/components/contact-form';
import PageHeader from '@/components/page-header';
import { urls } from '@/lib/urls';
import type {
    ClientOption,
    ContactFormValues,
    ContactListOption,
    TagOption,
} from '@/types';

type EditableContact = {
    id: number;
    first_name: string | null;
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
    dnd: boolean;
    contact_list_ids: number[];
    tag_ids: number[];
    custom_fields_json: string;
};

type PageProps = {
    contactLists: ContactListOption[];
    tags: TagOption[];
    clients: ClientOption[];
    contact: EditableContact;
};

/**
 * Normalises the persisted contact into the string based form shape.
 */
function initialValues(contact: EditableContact): Partial<ContactFormValues> {
    const text = (value: string | null): string => value ?? '';

    return {
        first_name: text(contact.first_name),
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
        dnd: contact.dnd,
        contact_list_ids: contact.contact_list_ids,
        tag_ids: contact.tag_ids,
        custom_fields_json: contact.custom_fields_json,
    };
}

export default function ContactsEdit({ contact, ...options }: PageProps) {
    return (
        <>
            <Head title={`Edit ${contact.first_name ?? contact.phone}`} />

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
                    tags={options.tags}
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
