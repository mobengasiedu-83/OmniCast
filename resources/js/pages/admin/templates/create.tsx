import { Head } from '@inertiajs/react';
import { LibraryBig } from 'lucide-react';
import PageHeader from '@/components/page-header';
import TemplateForm from '@/components/template-form';
import { urls } from '@/lib/urls';
import type { SelectOption, TemplateCategoryOption } from '@/types';

type PageProps = {
    categories: TemplateCategoryOption[];
    channels: SelectOption[];
    statuses: SelectOption[];
};

export default function AdminTemplatesCreate({
    categories,
    channels,
    statuses,
}: PageProps) {
    return (
        <>
            <Head title="New catalogue template" />

            <div className="flex flex-col gap-4">
                <PageHeader
                    icon={LibraryBig}
                    title="New catalogue template"
                    description="Add a starting point every client can pick. Catalogue copy belongs to the platform, so a client that changes it keeps its own copy."
                />

                <TemplateForm
                    action={urls.admin.templates.store}
                    method="post"
                    categories={categories}
                    channels={channels}
                    statuses={statuses}
                    submitLabel="Add to catalogue"
                    cancelHref={urls.admin.templates.index}
                    catalogue
                />
            </div>
        </>
    );
}

AdminTemplatesCreate.layout = {
    breadcrumbs: [
        {
            title: 'Catalogue',
            href: urls.admin.templates.index,
        },
        {
            title: 'New template',
            href: urls.admin.templates.create,
        },
    ],
};
