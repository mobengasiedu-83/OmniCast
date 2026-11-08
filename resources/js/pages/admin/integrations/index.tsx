import { Form, Head, Link } from '@inertiajs/react';
import { ExternalLink, Plug } from 'lucide-react';
import PageHeader from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { urls } from '@/lib/urls';
import { update as updateIntegrations } from '@/routes/admin/integrations';

type Integration = {
    value: string;
    label: string;
    description: string;
    docs_url: string | null;
    sample_head: string | null;
    sample_body: string | null;
    head: string | null;
    body: string | null;
    enabled: boolean;
};

export default function IntegrationSettings({
    integrations,
}: {
    integrations: Integration[];
}) {
    return (
        <>
            <Head title="Integrations" />
            <PageHeader
                icon={Plug}
                title="Integrations"
                description="Paste the tracking codes your marketing tools issue. An enabled snippet is printed on every page, the public site included, while a switched-off snippet stays saved but silent."
                actions={
                    <Button asChild variant="outline">
                        <Link href={urls.admin.settings.index}>
                            Back to settings
                        </Link>
                    </Button>
                }
            />
            <Form {...updateIntegrations.form()} className="mt-6 space-y-4">
                {({ processing }) => (
                    <>
                        {integrations.map((integration, index) => (
                            <section
                                key={integration.value}
                                className="space-y-4 rounded-lg border p-4"
                            >
                                <input
                                    type="hidden"
                                    name={`integrations[${index}][value]`}
                                    value={integration.value}
                                />
                                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div className="min-w-0 space-y-1">
                                        <p className="flex flex-wrap items-center gap-2 font-medium">
                                            {integration.label}
                                            <Badge
                                                variant={
                                                    integration.enabled
                                                        ? 'success'
                                                        : 'muted'
                                                }
                                            >
                                                {integration.enabled
                                                    ? 'Printed on every page'
                                                    : 'Saved but off'}
                                            </Badge>
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {integration.description}
                                        </p>
                                        {integration.docs_url !== null && (
                                            <a
                                                href={integration.docs_url}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="inline-flex items-center gap-1 text-xs font-medium text-brand hover:underline"
                                            >
                                                Where to find the code
                                                <ExternalLink className="size-3" />
                                            </a>
                                        )}
                                    </div>
                                    <div className="flex items-center gap-2 sm:pt-1">
                                        <input
                                            type="hidden"
                                            name={`integrations[${index}][enabled]`}
                                            value="0"
                                        />
                                        <input
                                            id={`integration-${integration.value}`}
                                            type="checkbox"
                                            name={`integrations[${index}][enabled]`}
                                            value="1"
                                            defaultChecked={integration.enabled}
                                        />
                                        <label
                                            htmlFor={`integration-${integration.value}`}
                                            className="text-sm"
                                        >
                                            Enabled
                                        </label>
                                    </div>
                                </div>
                                <div className="grid gap-4 lg:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label
                                            htmlFor={`head-${integration.value}`}
                                        >
                                            Head snippet
                                        </Label>
                                        <Textarea
                                            id={`head-${integration.value}`}
                                            name={`integrations[${index}][head]`}
                                            defaultValue={integration.head ?? ''}
                                            rows={6}
                                            spellCheck={false}
                                            placeholder={
                                                integration.sample_head ??
                                                'Nothing to load in the head.'
                                            }
                                            className="font-mono text-xs"
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label
                                            htmlFor={`body-${integration.value}`}
                                        >
                                            Body snippet
                                        </Label>
                                        <Textarea
                                            id={`body-${integration.value}`}
                                            name={`integrations[${index}][body]`}
                                            defaultValue={integration.body ?? ''}
                                            rows={6}
                                            spellCheck={false}
                                            placeholder={
                                                integration.sample_body ??
                                                'Nothing to load before the closing body tag.'
                                            }
                                            className="font-mono text-xs"
                                        />
                                    </div>
                                </div>
                            </section>
                        ))}
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <p className="max-w-2xl text-xs text-muted-foreground">
                                Snippets are printed exactly as pasted, so only
                                paste code from a vendor you trust. The head
                                snippet follows the page title; the body snippet
                                goes after the application markup.
                            </p>
                            <Button type="submit" disabled={processing}>
                                {processing
                                    ? 'Saving…'
                                    : 'Save integrations'}
                            </Button>
                        </div>
                    </>
                )}
            </Form>
        </>
    );
}
