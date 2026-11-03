import { Form, Head } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { review as reviewResourceRequest } from '@/routes/admin/resource-requests';

type ResourceRequest = { id: number; resource_type: string; name: string; details: string | null; status: string; review_notes: string | null; client: { name: string }; requester: { name: string } };
export default function ResourceRequests({ requests }: { requests: { data: ResourceRequest[] } }) {
    return <><Head title="Client resource requests" /><h1 className="text-2xl font-semibold">Client resource requests</h1><div className="mt-6 space-y-4">{requests.data.map((item) => <article key={item.id} className="rounded-lg border p-4"><div className="font-medium">{item.name} · {item.client.name}</div><p className="text-sm text-muted-foreground">{item.resource_type} requested by {item.requester.name} · {item.status}</p>{item.details && <p className="mt-2">{item.details}</p>}{item.status === 'pending' && <div className="mt-4 flex gap-3"><Form {...reviewResourceRequest.form(item.id)} className="flex gap-2"><input type="hidden" name="status" value="approved" /><Button>Approve</Button></Form><Form {...reviewResourceRequest.form(item.id)} className="flex gap-2"><input type="hidden" name="status" value="rejected" /><Button variant="outline">Reject</Button></Form></div>}{item.review_notes && <p className="mt-2 text-sm">Review notes: {item.review_notes}</p>}</article>)}</div></>;
}
