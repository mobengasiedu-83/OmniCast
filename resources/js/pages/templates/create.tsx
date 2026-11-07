import { Head } from '@inertiajs/react';
import { LayoutTemplate } from 'lucide-react';
import PageHeader from '@/components/page-header';
import TemplateForm from '@/components/template-form';
import { urls } from '@/lib/urls';
import type {
    ClientOption,
    SelectOption,
    TemplateCategoryOption,
} from '@/types';

type PageProps = {
    categories: TemplateCategoryOption[];
    channels: SelectOption[];
    statuses: SelectOption[];
    clients: ClientOption[];
    tenantClientId: number | null;
};

export default function TemplatesCreate({
    categories,
    channels,
    statuses,
    clients,
    tenantClientId,
}: PageProps) {
    return (
        <>
            <Head title="New template" />

            <div className="flex flex-col gap-4">
                <PageHeader
                    icon={LayoutTemplate}
                    title="New template"
                    description="Save reusable copy for a channel, then pick it in the campaign editor instead of retyping the message."
                />

                <TemplateForm
                    action={urls.templates.store}
                    method="post"
                    categories={categories}
                    channels={channels}
                    statuses={statuses}
                    clients={clients}
                    initial={{
                        client_id: tenantClientId ? String(tenantClientId) : '',
                    }}
                    submitLabel="Save template"
                    cancelHref={urls.templates.index}
                />
            </div>
        </>
    );
}

TemplatesCreate.layout = {
    breadcrumbs: [
        {
            title: 'Templates',
            href: urls.templates.index,
        },
        {
            title: 'New template',
            href: urls.templates.create,
        },
    ],
};
