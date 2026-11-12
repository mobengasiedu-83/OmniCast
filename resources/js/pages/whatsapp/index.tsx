import { Form, Head } from '@inertiajs/react';
import { MessageCircle, Upload } from 'lucide-react';
import PageHeader from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import whatsappRoutes from '@/routes/whatsapp';

type Connector = { id: number; connector_name: string | null; sender_number: string | null; waba_id: string | null; status: string };
type Assignment = { id: number; enabled: boolean; approval_status: string; connector: Connector };
type Template = { id: number; name: string; language: string; category: string | null; components: unknown; versions: { version: number; status: string; captured_at: string }[] };
type Media = { id: number; original_name: string; mime_type: string; size_bytes: number; created_at: string };

export default function WhatsApp({ connectors, availableConnectors, templates, media, maxUploadKilobytes }: {
    connectors: Assignment[]; availableConnectors: Connector[]; templates: Template[]; media: Media[]; maxUploadKilobytes: number;
}) {
    return <>
        <Head title="WhatsApp" />
        <PageHeader icon={MessageCircle} title="WhatsApp" description="Request sender access, review approved templates, and prepare media for future campaigns." />
        <div className="mt-6 space-y-6">
            <section className="rounded-lg border bg-card p-5">
                <h2 className="font-semibold">Assigned senders</h2>
                {connectors.length === 0 ? <p className="mt-3 text-sm text-muted-foreground">No sender access has been requested yet.</p> : <ul className="mt-3 divide-y">{connectors.map(({ id, connector, approval_status, enabled }) => <li key={id} className="flex flex-wrap items-center justify-between gap-3 py-3"><span>{connector.connector_name || 'WhatsApp sender'} · {connector.sender_number || 'Number unavailable'}</span><Badge variant={approval_status === 'approved' && enabled ? 'success' : 'muted'}>{enabled ? 'Enabled' : approval_status}</Badge></li>)}</ul>}
                {availableConnectors.length > 0 && <Form action={whatsappRoutes.connectors.request.url()} method="post" className="mt-4 flex flex-wrap items-end gap-3">{({ processing, errors }) => <><div className="grid gap-2"><Label htmlFor="connector_id">Request sender access</Label><select id="connector_id" name="connector_id" required className="h-10 rounded-md border bg-background px-3">{availableConnectors.map((connector) => <option key={connector.id} value={connector.id}>{connector.connector_name || connector.sender_number || `Sender ${connector.id}`}</option>)}</select>{errors.connector_id && <span className="text-sm text-destructive">{errors.connector_id}</span>}</div><Button disabled={processing}>Submit request</Button></>}</Form>}
            </section>
            <section className="rounded-lg border bg-card p-5"><h2 className="font-semibold">Approved templates</h2><p className="mt-1 text-sm text-muted-foreground">Only approved templates from enabled, approved sender assignments are shown.</p>{templates.length === 0 ? <p className="mt-3 text-sm text-muted-foreground">No approved templates are available.</p> : <div className="mt-4 grid gap-4 md:grid-cols-2">{templates.map((template) => <article key={template.id} className="rounded-md border p-4"><div className="flex items-center justify-between gap-2"><h3 className="font-medium">{template.name}</h3><Badge variant="success">Approved</Badge></div><p className="mt-1 text-sm text-muted-foreground">{template.language} · {template.category || 'Uncategorized'}</p><div className="mt-3 space-y-2 rounded bg-muted p-3">{previewComponents(template.components).map((component, index) => <p key={`${component.type}-${index}`} className={component.type === 'BODY' ? 'whitespace-pre-wrap text-sm' : 'text-xs font-medium uppercase text-muted-foreground'}>{component.text || `[${component.type} component]`}</p>)}</div><p className="mt-2 text-xs text-muted-foreground">{template.versions.length} saved version(s)</p></article>)}</div>}</section>
            <section className="rounded-lg border bg-card p-5"><h2 className="font-semibold">Media library</h2><p className="mt-1 text-sm text-muted-foreground">JPEG, PNG, WebP, MP4, and PDF up to {maxUploadKilobytes} KB.</p><Form action={whatsappRoutes.media.store.url()} method="post" encType="multipart/form-data" className="mt-4 flex flex-wrap items-end gap-3">{({ processing, errors }) => <><div className="grid gap-2"><Label htmlFor="file">Upload a file</Label><input id="file" type="file" name="file" required accept="image/jpeg,image/png,image/webp,video/mp4,application/pdf" className="max-w-full text-sm" />{errors.file && <span className="text-sm text-destructive">{errors.file}</span>}</div><Button disabled={processing}><Upload className="mr-2 size-4" />Upload</Button></>}</Form>{media.length > 0 && <ul className="mt-4 divide-y">{media.map((asset) => <li key={asset.id} className="flex justify-between gap-4 py-2 text-sm"><span>{asset.original_name} <span className="text-muted-foreground">({asset.mime_type})</span></span><span className="text-muted-foreground">{Math.ceil(asset.size_bytes / 1024)} KB</span></li>)}</ul>}</section>
        </div>
    </>;
}

function previewComponents(components: unknown): { type: string; text: string }[] {
    if (!Array.isArray(components)) return [];
    return components.flatMap((component) => {
        if (!component || typeof component !== 'object') return [];
        const item = component as { type?: unknown; text?: unknown; format?: unknown };
        const type = typeof item.type === 'string' ? item.type.toUpperCase() : 'TEMPLATE';
        const text = typeof item.text === 'string' ? item.text : typeof item.format === 'string' ? item.format : '';
        return [{ type, text }];
    });
}
