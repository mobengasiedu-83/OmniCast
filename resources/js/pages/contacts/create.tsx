import { Head } from '@inertiajs/react';
import { UserPlus } from 'lucide-react';
import ContactForm from '@/components/contact-form';
import PageHeader from '@/components/page-header';
import { urls } from '@/lib/urls';
import type { ClientOption, ContactListOption, SelectOption } from '@/types';

type PageProps = {
    contactLists: ContactListOption[];
    consentStatuses: SelectOption[];
    clients: ClientOption[];
    contact: null;
};

export default function ContactsCreate(props: PageProps) {
    return (
        <>
            <Head title="New contact" />

            <div className="flex flex-col gap-4">
                <PageHeader
                    icon={UserPlus}
                    title="New contact"
                    description="Add a single contact to the book."
                />

                <ContactForm
                    action={urls.contacts.store}
                    method="post"
                    contactLists={props.contactLists}
                    consentStatuses={props.consentStatuses}
                    clients={props.clients}
                    submitLabel="Add contact"
                    cancelHref={urls.contacts.index}
                />
            </div>
        </>
    );
}

ContactsCreate.layout = {
    breadcrumbs: [
        {
            title: 'Contacts',
            href: urls.contacts.index,
        },
        {
            title: 'New contact',
            href: urls.contacts.create,
        },
    ],
};
