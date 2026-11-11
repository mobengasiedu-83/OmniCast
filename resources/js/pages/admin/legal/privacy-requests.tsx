import { Head, router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import PageHeader from '@/components/page-header';
import Pagination from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    erase as erasePrivacyRequest,
    exportMethod as exportPrivacyRequest,
    review as reviewPrivacyRequest,
    store as storePrivacyRequest,
} from '@/actions/App/Http/Controllers/Admin/PrivacyRequestController';
import { urls } from '@/lib/urls';
import type { Paginated } from '@/types';

type ClientOption = {
    id: number;
    name: string;
    code: string;
};

type PrivacyRequestRow = {
    id: number;
    reference: string;
    client: string;
    client_code: string;
    subject_type: string;
    subject_label: string;
    subject: string;
    contact_id: number | null;
    action: string;
    action_label: string;
    status: string;
    status_label: string;
    requested_by: string;
    reviewed_by: string | null;
    completed_by: string | null;
    created_at: string | null;
    can_review: boolean;
    can_export: boolean;
    can_erase: boolean;
};

type PageProps = {
    clients: ClientOption[];
    requests: Paginated<PrivacyRequestRow>;
};

export default function PrivacyRequests({ clients, requests }: PageProps) {
    return (
        <>
            <Head title="Privacy requests" />

            <div className="grid gap-6">
                <PageHeader
                    title="Data subject requests"
                    description="Record, independently review and fulfil client contact or account access and erasure requests."
                />

                <p className="rounded-lg border border-border/60 bg-muted/40 px-4 py-3 text-sm text-muted-foreground">
                    A different administrator must review each request before
                    export or anonymisation. Account exports include profile,
                    member, contact and campaign-delivery metadata.
                    Anonymisation redacts active identifiers and delivery
                    payloads; financial, legal, suppression and audit records
                    are retained.
                </p>

                <CreatePrivacyRequestForm clients={clients} />

                <Card>
                    <CardHeader>
                        <CardTitle>Request register</CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Reference / subject</TableHead>
                                        <TableHead>Request</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Recorded by</TableHead>
                                        <TableHead className="text-right">
                                            Actions
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {requests.data.map((privacyRequest) => (
                                        <PrivacyRequestTableRow
                                            key={privacyRequest.id}
                                            privacyRequest={privacyRequest}
                                        />
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                        {requests.data.length === 0 && (
                            <p className="p-8 text-center text-sm text-muted-foreground">
                                No privacy requests have been recorded.
                            </p>
                        )}
                    </CardContent>
                </Card>

                <Pagination paginator={requests} noun="privacy requests" />
            </div>
        </>
    );
}

PrivacyRequests.layout = {
    breadcrumbs: [
        {
            title: 'Privacy requests',
            href: urls.admin.legal.privacyRequests.index,
        },
    ],
};

function CreatePrivacyRequestForm({ clients }: { clients: ClientOption[] }) {
    const form = useForm({
        client_id: '',
        subject_type: 'account',
        action: 'access',
        contact_id: '',
    });

    const submit = (event: FormEvent): void => {
        event.preventDefault();
        form.post(storePrivacyRequest.url(), {
            preserveScroll: true,
            onSuccess: () => form.reset('contact_id'),
        });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>Record an incoming request</CardTitle>
            </CardHeader>
            <CardContent>
                <form className="grid gap-4 sm:grid-cols-2" onSubmit={submit}>
                    <div className="grid gap-1.5">
                        <Label htmlFor="privacy-client">Client account</Label>
                        <Select
                            value={form.data.client_id}
                            onValueChange={(value) => {
                                form.setData('client_id', value)
                                form.setData('contact_id', '')
                            }}
                        >
                            <SelectTrigger id="privacy-client">
                                <SelectValue placeholder="Choose an account" />
                            </SelectTrigger>
                            <SelectContent>
                                {clients.map((client) => (
                                    <SelectItem
                                        key={client.id}
                                        value={String(client.id)}
                                    >
                                        {client.name} ({client.code})
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {form.errors.client_id && (
                            <p className="text-xs text-destructive">
                                {form.errors.client_id}
                            </p>
                        )}
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor="privacy-subject">Subject scope</Label>
                        <Select
                            value={form.data.subject_type}
                            onValueChange={(value) => {
                                form.setData(
                                    'subject_type',
                                    value === 'contact' ? 'contact' : 'account',
                                )
                                form.setData('contact_id', '')
                            }}
                        >
                            <SelectTrigger id="privacy-subject">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="account">
                                    Client account
                                </SelectItem>
                                <SelectItem value="contact">
                                    Individual contact
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor="privacy-action">Request type</Label>
                        <Select
                            value={form.data.action}
                            onValueChange={(value) =>
                                form.setData(
                                    'action',
                                    value === 'erasure' ? 'erasure' : 'access',
                                )
                            }
                        >
                            <SelectTrigger id="privacy-action">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="access">
                                    Data access export
                                </SelectItem>
                                <SelectItem value="erasure">
                                    Erasure / anonymisation
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    {form.data.subject_type === 'contact' && (
                        <div className="grid gap-1.5">
                            <Label htmlFor="privacy-contact">
                                Contact ID
                            </Label>
                            <Input
                                id="privacy-contact"
                                type="number"
                                min={1}
                                value={form.data.contact_id}
                                onChange={(event) =>
                                    form.setData(
                                        'contact_id',
                                        event.target.value,
                                    )
                                }
                            />
                            {form.errors.contact_id && (
                                <p className="text-xs text-destructive">
                                    {form.errors.contact_id}
                                </p>
                            )}
                        </div>
                    )}

                    <div className="sm:col-span-2">
                        <Button type="submit" disabled={form.processing}>
                            Record request
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}

function PrivacyRequestTableRow({
    privacyRequest,
}: {
    privacyRequest: PrivacyRequestRow;
}) {
    const eraseForm = useForm({ confirmation: '' });

    const review = (decision: 'approve' | 'reject'): void => {
        router.post(
            reviewPrivacyRequest.url(privacyRequest.id),
            { decision },
            { preserveScroll: true },
        );
    };

    const erase = (event: FormEvent): void => {
        event.preventDefault();
        eraseForm.post(
            erasePrivacyRequest.url(privacyRequest.id),
            { preserveScroll: true },
        );
    };

    return (
        <TableRow>
            <TableCell>
                <p className="font-mono text-xs">{privacyRequest.reference}</p>
                <p className="font-medium">{privacyRequest.subject}</p>
                <p className="text-xs text-muted-foreground">
                    {privacyRequest.client} ({privacyRequest.client_code})
                    {privacyRequest.contact_id
                        ? ` · Contact ${privacyRequest.contact_id}`
                        : ''}
                </p>
            </TableCell>
            <TableCell>
                <p>{privacyRequest.action_label}</p>
                <p className="text-xs text-muted-foreground">
                    {privacyRequest.subject_label}
                </p>
            </TableCell>
            <TableCell>
                <Badge variant={privacyRequest.status === 'completed' ? 'success' : 'secondary'}>
                    {privacyRequest.status_label}
                </Badge>
                {privacyRequest.reviewed_by && (
                    <p className="mt-1 text-xs text-muted-foreground">
                        Reviewed by {privacyRequest.reviewed_by}
                    </p>
                )}
            </TableCell>
            <TableCell>
                {privacyRequest.requested_by}
                <p className="text-xs text-muted-foreground">
                    {privacyRequest.created_at
                        ? new Date(privacyRequest.created_at).toLocaleDateString()
                        : '—'}
                </p>
            </TableCell>
            <TableCell className="min-w-60 text-right">
                <div className="flex flex-col items-end gap-2">
                    {privacyRequest.can_review && (
                        <div className="flex gap-2">
                            <Button
                                type="button"
                                size="sm"
                                onClick={() => review('approve')}
                            >
                                Approve
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                onClick={() => review('reject')}
                            >
                                Reject
                            </Button>
                        </div>
                    )}
                    {privacyRequest.can_export && (
                        <Button variant="outline" size="sm" asChild>
                            <a
                                href={exportPrivacyRequest.url(
                                    privacyRequest.id,
                                )}
                            >
                                Download approved export
                            </a>
                        </Button>
                    )}
                    {privacyRequest.can_erase && (
                        <form className="grid gap-1.5" onSubmit={erase}>
                            <Label
                                htmlFor={`confirm-${privacyRequest.id}`}
                                className="text-left text-xs"
                            >
                                Type the reference to confirm anonymisation
                            </Label>
                            <Input
                                id={`confirm-${privacyRequest.id}`}
                                value={eraseForm.data.confirmation}
                                onChange={(event) =>
                                    eraseForm.setData(
                                        'confirmation',
                                        event.target.value,
                                    )
                                }
                                className="font-mono text-xs"
                            />
                            {eraseForm.errors.confirmation && (
                                <p className="text-left text-xs text-destructive">
                                    {eraseForm.errors.confirmation}
                                </p>
                            )}
                            <Button
                                type="submit"
                                size="sm"
                                variant="destructive"
                                disabled={eraseForm.processing}
                            >
                                Anonymise approved subject
                            </Button>
                        </form>
                    )}
                </div>
            </TableCell>
        </TableRow>
    );
}
