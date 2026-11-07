import { Head } from '@inertiajs/react';
import { LayoutTemplate } from 'lucide-react';
import PageHeader from '@/components/page-header';
import TemplateForm from '@/components/template-form';
import { urls } from '@/lib/urls';
import type {
    ClientOption,
    SelectOption,
    TemplateCategoryOption,
    TemplateRow,
} from '@/types';

type PageProps = {
    categories: TemplateCategoryOption[];
    channels: SelectOption[];
    statuses: SelectOption[];
    clients: ClientOption[];
    tenantClientId: number | null;
    template: TemplateRow;
};

/** The statuses the editor may save, so a reviewed template reopens cleanly. */
const editableStatuses = new Set(['draft', 'pending', 'active']);

export default function TemplatesEdit({
    categories,
    channels,
    statuses,
    clients,
    template,
}: PageProps) {
    return (
        <>
            <Head title={`Edit ${template.name}`} />

            <div className="flex flex-col gap-4">
                <PageHeader
                    icon={LayoutTemplate}
                    title={`Edit ${template.name}`}
                    description={
                        template.is_system
                            ? 'This is the platform catalogue copy. Saving changes it for every client that has not customised it yet.'
                            : 'Changes reach the campaigns saved from this template from now on; campaigns already created keep the copy they started with.'
                    }
                />

                <TemplateForm
                    action={urls.templates.update(template.id)}
                    method="put"
                    categories={categories}
                    channels={channels}
                    statuses={statuses}
                    clients={clients}
                    initial={{
                        name: template.name,
                        channel: template.channel,
                        template_category_id: template.category
                            ? String(template.category.id)
                            : '',
                        language: template.language,
                        description: template.description ?? '',
                        subject: template.subject ?? '',
                        body: template.body,
                        status: editableStatuses.has(template.status)
                            ? template.status
                            : 'draft',
                        client_id: template.client_id
                            ? String(template.client_id)
                            : '',
                    }}
                    submitLabel="Save changes"
                    cancelHref={urls.templates.index}
                    deleteUrl={
                        template.can.delete
                            ? urls.templates.destroy(template.id)
                            : undefined
                    }
                />
            </div>
        </>
    );
}

TemplatesEdit.layout = {
    breadcrumbs: [
        {
            title: 'Templates',
            href: urls.templates.index,
        },
        {
            title: 'Edit template',
            href: urls.templates.index,
        },
    ],
};
