import { Form, Head } from '@inertiajs/react';
import { Radio } from 'lucide-react';
import PageHeader from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import whatsappRoutes from '@/routes/admin/whatsapp';

type Connector = { id: number; connector_name: string | null; sender_number: string | null; waba_id: string | null; status: string; templates_count: number; provider: { id: number; name: string } };
type Assignment = { id: number; enabled: boolean; approval_status: string; client: { id: number; name: string }; connector: Connector };
type Provider = { id: number; name: string };

export default function WhatsAppConnectors({ connectors, assignments, providers }: { connectors: Connector[]; assignments: Assignment[]; providers: Provider[] }) {
    return <>
        <Head title="WhatsApp connectors" />
        <PageHeader icon={Radio} title="WhatsApp connectors" description="Synchronize provider senders and templates, then review tenant access." />
        <div className="mt-6 space-y-6">
            {providers.map((provider) => <Form key={provider.id} action={whatsappRoutes.connectors.sync.url(provider.id)} method="post"><Button variant="outline">Sync connectors from {provider.name}</Button></Form>)}
            <section className="rounded-lg border bg-card p-5"><h2 className="font-semibold">Provider senders</h2><div className="mt-3 divide-y">{connectors.map((connector) => <div key={connector.id} className="flex flex-wrap items-center justify-between gap-3 py-3"><div><p className="font-medium">{connector.connector_name || connector.sender_number || `Sender ${connector.id}`}</p><p className="text-sm text-muted-foreground">{connector.provider.name} · WABA {connector.waba_id || 'unknown'} · {connector.templates_count} templates</p></div><div className="flex items-center gap-2"><Badge variant={connector.status === 'available' ? 'success' : 'muted'}>{connector.status}</Badge><Form action={whatsappRoutes.connectors.templates.sync.url(connector.id)} method="post"><Button size="sm" variant="outline">Sync templates</Button></Form></div></div>)}</div></section>
            <section className="rounded-lg border bg-card p-5"><h2 className="font-semibold">Client sender access</h2>{assignments.length === 0 ? <p className="mt-3 text-sm text-muted-foreground">No client requests.</p> : <div className="mt-3 divide-y">{assignments.map((assignment) => <div key={assignment.id} className="flex flex-wrap items-center justify-between gap-3 py-3"><div><p className="font-medium">{assignment.client.name} · {assignment.connector.connector_name || assignment.connector.sender_number}</p><p className="text-sm text-muted-foreground">{assignment.connector.sender_number} · {assignment.approval_status}{assignment.enabled ? ' · enabled' : ''}</p></div><div className="flex gap-2">{assignment.approval_status === 'pending' && <><ReviewButton id={assignment.id} status="approved" label="Approve" /><ReviewButton id={assignment.id} status="rejected" label="Reject" /></>}{assignment.approval_status === 'approved' && <Form action={whatsappRoutes.connectorAssignments.toggle.url(assignment.id)} method="patch"><Button size="sm" variant="outline">{assignment.enabled ? 'Disable' : 'Enable'}</Button></Form>}</div></div>)}</div>}</section>
        </div>
    </>;
}

function ReviewButton({ id, status, label }: { id: number; status: 'approved' | 'rejected'; label: string }) {
    return <Form action={whatsappRoutes.connectorAssignments.review.url(id)} method="put"><input type="hidden" name="status" value={status} /><Button size="sm" variant={status === 'approved' ? 'default' : 'outline'}>{label}</Button></Form>;
}
