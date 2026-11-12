import { Form, Head } from '@inertiajs/react';
import { Plug } from 'lucide-react';
import PageHeader from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store as storeProvider, test as testProvider, update as updateProvider } from '@/routes/admin/whatsapp/providers';

type Provider = {
    id: number;
    name: string;
    driver: string;
    status: string;
    status_label: string;
    is_enabled: boolean;
    connect_timeout: number;
    request_timeout: number;
    tested_at: string | null;
    last_test_error: string | null;
};

export default function WhatsAppProviders({
    providers,
    can,
    sendingEnabled,
    providerConfiguration,
}: {
    providers: Provider[];
    can: { manage: boolean; test: boolean };
    sendingEnabled: boolean;
    providerConfiguration: { baseUrl: string | null; credentialsConfigured: boolean; endpointsConfigured: boolean };
}) {
    return (
        <>
            <Head title="WhatsApp providers" />
            <PageHeader
                icon={Plug}
                title="WhatsApp providers"
                description="Manage the platform provider. Shared WhatsApp endpoints and credentials are configured through the server environment."
            />

            <div className="mt-6 space-y-6">
                <section className="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950/50 dark:text-amber-100">
                    <strong>{sendingEnabled ? 'Message sending is enabled.' : 'Message sending is disabled.'}</strong>{' '}
                    {sendingEnabled
                        ? 'Only use this setting after provider sandbox and launch checks are complete.'
                        : 'Connection checks are available; message sending remains off until the provider contract and sandbox are confirmed.'}
                </section>

                <section className="rounded-lg border bg-card p-4 text-sm">
                    <h2 className="font-semibold">Shared environment configuration</h2>
                    <p className="mt-1 text-muted-foreground">Base URL: {providerConfiguration.baseUrl || 'Not configured'}</p>
                    <p className="mt-1 text-muted-foreground">Credentials: {providerConfiguration.credentialsConfigured ? 'Configured in environment' : 'Missing environment values'}</p>
                    <p className="mt-1 text-muted-foreground">Endpoint paths: {providerConfiguration.endpointsConfigured ? 'Configured' : 'Missing environment values'}</p>
                </section>

                {can.manage && (
                    <section className="rounded-lg border bg-card p-5">
                        <h2 className="text-lg font-semibold">Add provider</h2>
                        <Form action={storeProvider.url()} method="post" className="mt-4 grid gap-4 md:grid-cols-2">
                            {({ processing, errors }) => (
                                <>
                                    <input type="hidden" name="driver" value="pro_plus" />
                                    <ProviderFields errors={errors} />
                                    <div className="md:col-span-2">
                                        <Button type="submit" disabled={processing}>Save provider</Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </section>
                )}

                <div className="space-y-4">
                    {providers.length === 0 && (
                        <section className="rounded-lg border bg-card p-6 text-sm text-muted-foreground">
                            No WhatsApp provider is configured.
                        </section>
                    )}
                    {providers.map((provider) => (
                        <section key={provider.id} className="rounded-lg border bg-card p-5">
                            <div className="mb-4 flex flex-wrap items-center gap-2">
                                <h2 className="text-lg font-semibold">{provider.name}</h2>
                                <Badge variant={provider.status === 'connected' ? 'success' : 'muted'}>{provider.status_label}</Badge>
                                <Badge variant={provider.is_enabled ? 'success' : 'muted'}>{provider.is_enabled ? 'Enabled' : 'Disabled'}</Badge>
                            </div>
                            {can.manage ? (
                                <>
                                    <Form action={updateProvider.url(provider.id)} method="put" className="grid gap-4 md:grid-cols-2">
                                        {({ processing, errors }) => (
                                            <>
                                                <input type="hidden" name="driver" value={provider.driver} />
                                                <ProviderFields provider={provider} errors={errors} />
                                                <div className="flex items-center gap-2 md:col-span-2">
                                                    <Button type="submit" disabled={processing}>Save changes</Button>
                                                </div>
                                            </>
                                        )}
                                    </Form>
                                    {can.test && (
                                        <Form action={testProvider.url(provider.id)} method="post" className="mt-3">
                                            {({ processing: testing }) => (
                                                <Button type="submit" variant="outline" disabled={testing}>Test connection</Button>
                                            )}
                                        </Form>
                                    )}
                                </>
                            ) : (
                                <p className="text-sm text-muted-foreground">{providerConfiguration.baseUrl || 'Shared endpoint is not configured'}</p>
                            )}
                            <p className="mt-3 text-xs text-muted-foreground">
                                Last tested: {provider.tested_at ?? 'Never'}
                            </p>
                            {provider.last_test_error && <p className="mt-2 text-sm text-destructive">{provider.last_test_error}</p>}
                        </section>
                    ))}
                </div>
            </div>
        </>
    );
}

function ProviderFields({
    provider,
    errors,
}: {
    provider?: Provider;
    errors: Record<string, string>;
}) {
    return (
        <>
            <Field idPrefix={`name-${provider?.id ?? 'new'}`} name="name" label="Provider name" defaultValue={provider?.name ?? ''} error={errors.name} />
            <Field idPrefix={`connect-timeout-${provider?.id ?? 'new'}`} name="connect_timeout" label="Connect timeout (seconds)" type="number" defaultValue={provider?.connect_timeout ?? 5} error={errors.connect_timeout} />
            <Field idPrefix={`request-timeout-${provider?.id ?? 'new'}`} name="request_timeout" label="Request timeout (seconds)" type="number" defaultValue={provider?.request_timeout ?? 20} error={errors.request_timeout} />
            <div className="flex items-center gap-2 md:col-span-2">
                <input type="hidden" name="is_enabled" value="0" />
                <input id={`provider-enabled-${provider?.id ?? 'new'}`} type="checkbox" name="is_enabled" value="1" defaultChecked={provider?.is_enabled ?? false} />
                <Label htmlFor={`provider-enabled-${provider?.id ?? 'new'}`}>Enable provider for delivery</Label>
                {errors.is_enabled && <span className="text-sm text-destructive">{errors.is_enabled}</span>}
            </div>
        </>
    );
}

function Field({
    idPrefix,
    name,
    label,
    defaultValue,
    error,
    type = 'text',
    autoComplete,
    placeholder,
}: {
    idPrefix: string;
    name: string;
    label: string;
    defaultValue?: string | number;
    error?: string;
    type?: string;
    autoComplete?: string;
    placeholder?: string;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={`${name}-${idPrefix}`}>{label}</Label>
            <Input id={`${name}-${idPrefix}`} name={name} type={type} defaultValue={defaultValue} autoComplete={autoComplete} placeholder={placeholder} />
            {error && <p className="text-sm text-destructive">{error}</p>}
        </div>
    );
}
