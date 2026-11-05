import { Form, Head } from '@inertiajs/react';
import { type MouseEvent } from 'react';
import { useConfirm, type ConfirmOptions } from '@/components/confirm-dialog';
import PageHeader from '@/components/page-header';
import Pagination from '@/components/pagination';
import StatCard from '@/components/stat-card';
import StatusBadge from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import clientsRoute from '@/routes/admin/clients';
import registrationRoutes from '@/routes/admin/client-registrations';
import type { Paginated } from '@/types';

type Registration = { id: number; company_name: string | null; account_type: string; account_type_label: string; contact_name: string; email: string; phone: string; country: string | null; timezone: string; website: string | null; services: string[]; expected_monthly_volume: number | null; message: string | null; status: string; status_label: string; is_pending: boolean; client: string | null; reviewer: string | null; rejection_reason: string | null; created_at: string | null };
type Props = { registrations: Paginated<Registration>; filters: { search: string; status: string }; stats: { pending: number; approved: number; rejected: number; total: number }; statuses: { value: string; label: string }[]; services: { value: string; label: string }[] };

export default function ClientRegistrations({ registrations, stats }: Props) {
    const confirm = useConfirm();

    /**
     * Rejections live inside Inertia <Form>s, so the native submit is
     * cancelled and re-dispatched once the shared dialog is accepted. The
     * rejection reason is validated before the dialog opens.
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

    return <>
        <Head title="Client registrations" />
        <div className="grid gap-6"><PageHeader title="Client registrations" description="Review prospective client applications and create pending tenant accounts." />
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"><StatCard title="Awaiting review" value={stats.pending} /><StatCard title="Approved" value={stats.approved} /><StatCard title="Rejected" value={stats.rejected} /><StatCard title="All applications" value={stats.total} /></div>
            <Card className="overflow-hidden"><CardContent className="p-0"><Table><TableHeader><TableRow><TableHead>Applicant</TableHead><TableHead>Account</TableHead><TableHead>Requested services</TableHead><TableHead>Status</TableHead><TableHead>Decision</TableHead></TableRow></TableHeader><TableBody>{registrations.data.map((registration) => <TableRow key={registration.id}><TableCell>{registration.contact_name}<p className="text-xs text-muted-foreground">{registration.email}<br />{registration.phone}</p></TableCell><TableCell className="font-medium">{registration.account_type_label}{registration.company_name && <p className="text-xs text-muted-foreground">{registration.company_name}</p>}<p className="text-xs text-muted-foreground">{registration.country ?? '—'} · {registration.timezone}</p>{registration.website && <a className="text-xs underline" href={registration.website} target="_blank" rel="noreferrer">Website</a>}</TableCell><TableCell>{registration.services.join(', ') || 'No preference'}<p className="text-xs text-muted-foreground">{registration.expected_monthly_volume?.toLocaleString() ?? '—'} recipients/month</p></TableCell><TableCell><StatusBadge status={registration.status} label={registration.status_label} />{registration.rejection_reason && <p className="mt-1 max-w-48 text-xs text-muted-foreground">{registration.rejection_reason}</p>}</TableCell><TableCell>{registration.is_pending ? <div className="grid gap-2"><Form {...registrationRoutes.approve.form(registration.id)} className="flex flex-wrap gap-2">{({ processing }) => <><Input name="name" defaultValue={registration.company_name ?? registration.contact_name} aria-label="Tenant name" className="h-8 min-w-32" /><Button size="sm" disabled={processing}>Approve</Button></>}</Form><Form {...registrationRoutes.reject.form(registration.id)} className="grid gap-2">{({ processing }) => <><Textarea name="reason" placeholder="Reason for rejection (required)" rows={2} required minLength={5} /><Button size="sm" variant="outline" disabled={processing} onClick={(event) => guardSubmit(event, { title: `Reject the application from ${registration.company_name ?? registration.contact_name}?`, description: 'The application is closed with the reason you supplied. The applicant has to submit a new application to be reconsidered.', confirmLabel: 'Reject application', tone: 'danger' })}>Reject</Button></>}</Form></div> : <span className="text-xs text-muted-foreground">{registration.client ?? registration.reviewer ?? 'Reviewed'}</span>}</TableCell></TableRow>)}</TableBody></Table>{registrations.data.length === 0 && <p className="p-8 text-center text-sm text-muted-foreground">No applications to review.</p>}</CardContent></Card><Pagination paginator={registrations} noun="applications" />
        </div>
    </>;
}

ClientRegistrations.layout = { breadcrumbs: [{ title: 'Clients', href: clientsRoute.index.url() }, { title: 'Registrations', href: registrationRoutes.index.url() }] };
