import { Head } from '@inertiajs/react';
import { Fingerprint } from 'lucide-react';
import PageHeader from '@/components/page-header';
import SenderIdForm from '@/components/sender-id-form';
import { urls } from '@/lib/urls';
import type { ClientOption, SenderIdChannelOption } from '@/types';

type PageProps = {
    channels: SenderIdChannelOption[];
    clients: ClientOption[];
    tenantClientId: number | null;
};

export default function SenderIdsCreate({ channels, clients, tenantClientId }: PageProps) {
    return (
        <>
            <Head title="Request sender ID" />

            <div className="flex flex-col gap-4">
                <PageHeader
                    icon={Fingerprint}
                    title="Request sender ID"
                    description="Ask compliance for an SMS sender ID, email from address or voice caller number."
                />

                <SenderIdForm
                    action={urls.senderIds.store}
                    method="post"
                    channels={channels}
                    clients={clients}
                    initial={{ client_id: tenantClientId ? String(tenantClientId) : '' }}
                    submitLabel="Submit for review"
                    cancelHref={urls.senderIds.index}
                />
            </div>
        </>
    );
}

SenderIdsCreate.layout = {
    breadcrumbs: [
        {
            title: 'Sender IDs',
            href: urls.senderIds.index,
        },
        {
            title: 'Request sender ID',
            href: urls.senderIds.create,
        },
    ],
};
