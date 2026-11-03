import { Head, Link, router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import PageHeader from '@/components/page-header';
import Pagination from '@/components/pagination';
import StatCard from '@/components/stat-card';
import StatusBadge from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import clientsRoute from '@/routes/admin/clients';
import registrationsRoute from '@/routes/admin/client-registrations';
import type { Paginated, SelectOption } from '@/types';

type ClientRow = { id: number; name: string; code: string; email: string | null; phone: string | null; status: string; status_label: string; status_reason: string | null; users_count: number; documents_count: number; pending_documents_count: number; services: string[] };
type Props = { clients: Paginated<ClientRow>; filters: { search: string; status: string; view: string }; stats: { total: number; pending: number; active: number; suspended: number; archived: number; applications: number }; statuses: SelectOption[]; can: { review: boolean } };

export default function ClientsIndex({ clients, filters, stats, statuses, can }: Props) {
    const [search, setSearch] = useState(filters.search);
    const apply = (event?: FormEvent): void => {
        event?.preventDefault();
        router.get(clientsRoute.index.url(), { search: search || null, status: filters.status || null, view: filters.view || null }, { preserveState: true, replace: true });
    };
    return <>
        <Head title="Clients" />
        <div className="grid gap-6">
            <PageHeader title="Clients" description="Review tenant status, compliance documents and service access." actions={can.review ? <Button asChild variant="outline"><Link href={registrationsRoute.index.url()}>Registration queue ({stats.applications})</Link></Button> : undefined} />
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                <StatCard title="All clients" value={stats.total} /><StatCard title="Pending" value={stats.pending} /><StatCard title="Active" value={stats.active} /><StatCard title="Suspended" value={stats.suspended} /><StatCard title="Archived" value={stats.archived} />
            </div>
            <div className="flex flex-wrap gap-2">{[['', 'All'], ['pending', 'Pending'], ['active', 'Active'], ['suspended', 'Suspended'], ['archived', 'Archived']].map(([status, label]) => <Button key={status || 'all'} size="sm" variant={filters.status === status && !filters.view ? 'default' : 'outline'} onClick={() => router.get(clientsRoute.index.url(), { search: search || null, status: status || null, view: null }, { preserveState: true, replace: true })}>{label}</Button>)}</div>
            <Card><CardContent className="flex flex-col gap-3 p-4 sm:flex-row">
                <form onSubmit={apply} className="flex flex-1 gap-2"><Input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Search company, code or email" /><Button type="submit" variant="secondary">Search</Button></form>
                <Select value={filters.view || 'all'} onValueChange={(value) => { router.get(clientsRoute.index.url(), { search: search || null, status: filters.status || null, view: value === 'all' ? null : value }, { preserveState: true, replace: true }); }}><SelectTrigger className="sm:w-56"><SelectValue placeholder="All client records" /></SelectTrigger><SelectContent><SelectItem value="all">All client records</SelectItem><SelectItem value="documents">With documents</SelectItem><SelectItem value="users">With users</SelectItem><SelectItem value="services">With service entitlements</SelectItem></SelectContent></Select>
            </CardContent></Card>
            <Card className="overflow-hidden"><CardContent className="p-0"><Table><TableHeader><TableRow><TableHead>Organisation</TableHead><TableHead>Contact</TableHead><TableHead>Status</TableHead><TableHead>Services</TableHead><TableHead>Compliance</TableHead><TableHead>Users</TableHead></TableRow></TableHeader><TableBody>{clients.data.map((client) => <TableRow key={client.id}><TableCell><Link className="font-medium hover:underline" href={clientsRoute.show.url(client.id)}>{client.name}</Link><p className="text-xs text-muted-foreground">{client.code}</p></TableCell><TableCell>{client.email ?? '—'}<p className="text-xs text-muted-foreground">{client.phone ?? '—'}</p></TableCell><TableCell><StatusBadge status={client.status} label={client.status_label} /></TableCell><TableCell>{client.services.length ? client.services.join(', ') : 'Not configured'}</TableCell><TableCell>{client.pending_documents_count} awaiting · {client.documents_count} total</TableCell><TableCell>{client.users_count}</TableCell></TableRow>)}</TableBody></Table>{clients.data.length === 0 && <p className="p-8 text-center text-sm text-muted-foreground">No client accounts match these filters.</p>}</CardContent></Card>
            <Pagination paginator={clients} noun="clients" />
        </div>
    </>;
}

ClientsIndex.layout = { breadcrumbs: [{ title: 'Clients', href: clientsRoute.index.url() }] };
