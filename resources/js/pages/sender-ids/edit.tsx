import { Head } from '@inertiajs/react';
import { Fingerprint } from 'lucide-react';
import PageHeader from '@/components/page-header';
import SenderIdForm from '@/components/sender-id-form';
import { urls } from '@/lib/urls';
import type { ClientOption, SenderIdChannelOption, SenderIdRow } from '@/types';

type PageProps = {
    channels: SenderIdChannelOption[];
    clients: ClientOption[];
    tenantClientId: number | null;
    senderId: SenderIdRow & { client_id: number };
};

export default function SenderIdsEdit({
    channels,
    clients,
    senderId,
}: PageProps) {
    return (
        <>
            <Head title={`Edit ${senderId.name}`} />

            <div className="flex flex-col gap-4">
                <PageHeader
                    icon={Fingerprint}
                    title={`Edit ${senderId.name}`}
                    description="Changes are sent back to compliance for review before they take effect."
                />

                <SenderIdForm
                    action={urls.senderIds.update(senderId.id)}
                    method="put"
                    channels={channels}
                    clients={clients}
                    initial={{
                        channel: senderId.channel,
                        name: senderId.name,
                        country: senderId.country ?? '',
                        purpose: senderId.purpose ?? '',
                        client_id: String(senderId.client_id),
                    }}
                    submitLabel="Resubmit for review"
                    cancelHref={urls.senderIds.index}
                />
            </div>
        </>
    );
}

SenderIdsEdit.layout = {
    breadcrumbs: [
        {
            title: 'Sender IDs',
            href: urls.senderIds.index,
        },
        {
            title: 'Edit request',
            href: urls.senderIds.index,
        },
    ],
};
