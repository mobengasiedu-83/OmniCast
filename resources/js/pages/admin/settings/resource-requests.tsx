import { Form, Head } from '@inertiajs/react';
import { type MouseEvent } from 'react';
import { useConfirm, type ConfirmOptions } from '@/components/confirm-dialog';
import { Button } from '@/components/ui/button';
import { review as reviewResourceRequest } from '@/routes/admin/resource-requests';

type ResourceRequest = { id: number; resource_type: string; name: string; details: string | null; status: string; review_notes: string | null; client: { name: string }; requester: { name: string } };
export default function ResourceRequests({ requests }: { requests: { data: ResourceRequest[] } }) {
    const confirm = useConfirm();

    /**
     * Rejections live inside Inertia <Form>s, so the native submit is
     * cancelled and re-dispatched once the shared dialog is accepted.
     */
    const guardSubmit = (event: MouseEvent<HTMLButtonElement>, options: ConfirmOptions): void => {
        event.preventDefault();
        const form = event.currentTarget.form;

        if (form !== null && !form.reportValidity()) {
            return;
        }

        void confirm(options).then((confirmed) => {
            if (confirmed) {
                form?.requestSubmit();
            }
        });
    };

    return <><Head title="Client resource requests" /><h1 className="text-2xl font-semibold">Client resource requests</h1><div className="mt-6 space-y-4">{requests.data.map((item) => <article key={item.id} className="rounded-lg border p-4"><div className="font-medium">{item.name} · {item.client.name}</div><p className="text-sm text-muted-foreground">{item.resource_type} requested by {item.requester.name} · {item.status}</p>{item.details && <p className="mt-2">{item.details}</p>}{item.status === 'pending' && <div className="mt-4 flex gap-3"><Form {...reviewResourceRequest.form(item.id)} className="flex gap-2"><input type="hidden" name="status" value="approved" /><Button>Approve</Button></Form><Form {...reviewResourceRequest.form(item.id)} className="flex gap-2"><input type="hidden" name="status" value="rejected" /><Button variant="outline" onClick={(event) => guardSubmit(event, { title: `Reject ${item.name}?`, description: `${item.client.name} sees the request closed as rejected. Only pending requests can be reviewed, so they have to raise a new one.`, confirmLabel: 'Reject request', tone: 'danger' })}>Reject</Button></Form></div>}{item.review_notes && <p className="mt-2 text-sm">Review notes: {item.review_notes}</p>}</article>)}</div></>;
}
