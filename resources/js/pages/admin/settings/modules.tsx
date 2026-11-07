import { Form, Head, Link } from '@inertiajs/react';
import { Blocks } from 'lucide-react';
import PageHeader from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { urls } from '@/lib/urls';
import { update as updateModules } from '@/routes/admin/settings/modules';

type Module = {
    value: string;
    label: string;
    description: string;
    enabled: boolean;
    clients_disabled: number;
};

export default function ModuleSettings({ modules }: { modules: Module[] }) {
    return (
        <>
            <Head title="Module availability" />
            <PageHeader
                icon={Blocks}
                title="Module availability"
                description="Switch a delivery module off for every client on the platform. A module switched off here disappears for the whole platform, whatever a tenant has chosen for its own account."
                actions={
                    <Button asChild variant="outline">
                        <Link href={urls.admin.settings.index}>
                            Back to settings
                        </Link>
                    </Button>
                }
            />
            {modules.length === 0 ? (
                <p className="mt-6 rounded-lg border border-dashed p-6 text-sm text-muted-foreground">
                    The platform does not deliver any modules yet.
                </p>
            ) : (
                <Form {...updateModules.form()} className="mt-6 space-y-4">
                    {({ processing }) => (
                        <>
                            {modules.map((module, index) => (
                                <section
                                    key={module.value}
                                    className="flex flex-col gap-3 rounded-lg border p-4 sm:flex-row sm:items-start sm:justify-between"
                                >
                                    <input
                                        type="hidden"
                                        name={`modules[${index}][value]`}
                                        value={module.value}
                                    />
                                    <div className="min-w-0 space-y-1">
                                        <p className="flex flex-wrap items-center gap-2 font-medium">
                                            {module.label}
                                            <Badge
                                                variant={
                                                    module.enabled
                                                        ? 'success'
                                                        : 'destructive'
                                                }
                                            >
                                                {module.enabled
                                                    ? 'Live platform-wide'
                                                    : 'Off platform-wide'}
                                            </Badge>
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {module.description}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {module.clients_disabled === 0
                                                ? 'No client has this module switched off for its own account.'
                                                : `${module.clients_disabled} client${module.clients_disabled === 1 ? '' : 's'} switched it off for their own account.`}
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-2 sm:pt-1">
                                        <input
                                            type="hidden"
                                            name={`modules[${index}][enabled]`}
                                            value="0"
                                        />
                                        <input
                                            id={`module-${module.value}`}
                                            type="checkbox"
                                            name={`modules[${index}][enabled]`}
                                            value="1"
                                            defaultChecked={module.enabled}
                                        />
                                        <label
                                            htmlFor={`module-${module.value}`}
                                            className="text-sm"
                                        >
                                            Enabled for every client
                                        </label>
                                    </div>
                                </section>
                            ))}
                            <Button type="submit" disabled={processing}>
                                {processing
                                    ? 'Saving…'
                                    : 'Save module availability'}
                            </Button>
                        </>
                    )}
                </Form>
            )}
        </>
    );
}
