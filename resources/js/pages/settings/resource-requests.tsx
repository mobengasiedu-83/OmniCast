import { Form, Head } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store as createResourceRequest } from '@/routes/organisation-settings/requests';

type ResourceRequest = { id: number; resource_type: string; name: string; details: string | null; status: string; review_notes: string | null; created_at: string };
export default function ResourceRequests({ requests }: { requests: { data: ResourceRequest[] } }) {
    return <>
        <Head title="Resource requests" />
        <h1 className="text-2xl font-semibold">Resource requests</h1>
        <Form {...createResourceRequest.form()} className="my-6 grid max-w-xl gap-3 rounded-lg border p-4">
            <Label htmlFor="resource_type">Resource type</Label><select id="resource_type" name="resource_type" className="rounded-md border bg-background px-3 py-2"><option value="caller_id">Caller ID</option><option value="sender_id">SMS sender ID</option><option value="sending_domain">Sending domain</option><option value="other">Other</option></select>
            <Label htmlFor="name">Requested resource</Label><Input id="name" name="name" required />
            <Label htmlFor="details">Details</Label><textarea id="details" name="details" className="rounded-md border bg-background p-3" />
            <Button>Submit request</Button>
        </Form>
        <div className="space-y-3">{requests.data.map((item) => <article key={item.id} className="rounded-lg border p-4"><div className="font-medium">{item.name} <span className="text-sm text-muted-foreground">· {item.status}</span></div><p>{item.resource_type}</p>{item.details && <p className="text-sm">{item.details}</p>}{item.review_notes && <p className="mt-2 text-sm">Review: {item.review_notes}</p>}</article>)}</div>
    </>;
}
