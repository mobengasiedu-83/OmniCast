import { Head } from '@inertiajs/react';
import { LibraryBig } from 'lucide-react';
import PageHeader from '@/components/page-header';
import TemplateForm from '@/components/template-form';
import { urls } from '@/lib/urls';
import type {
    SelectOption,
    TemplateCategoryOption,
    TemplateRow,
} from '@/types';

type PageProps = {
    categories: TemplateCategoryOption[];
    channels: SelectOption[];
    statuses: SelectOption[];
    template: TemplateRow;
};

/** The statuses the catalogue editor may save. */
const editableStatuses = new Set(['draft', 'pending', 'active']);

export default function AdminTemplatesEdit({
    categories,
    channels,
    statuses,
    template,
}: PageProps) {
    return (
        <>
            <Head title={`Edit ${template.name}`} />

            <div className="flex flex-col gap-4">
                <PageHeader
                    icon={LibraryBig}
                    title={`Edit ${template.name}`}
                    description="Every client that has not customised this template sees the copy you save here."
                />

                <TemplateForm
                    action={urls.admin.templates.update(template.id)}
                    method="put"
                    categories={categories}
                    channels={channels}
                    statuses={statuses}
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
                        client_id: '',
                    }}
                    submitLabel="Save catalogue entry"
                    cancelHref={urls.admin.templates.index}
                    deleteUrl={urls.admin.templates.destroy(template.id)}
                    catalogue
                />
            </div>
        </>
    );
}

AdminTemplatesEdit.layout = {
    breadcrumbs: [
        {
            title: 'Catalogue',
            href: urls.admin.templates.index,
        },
        {
            title: 'Edit template',
            href: urls.admin.templates.index,
        },
    ],
};
