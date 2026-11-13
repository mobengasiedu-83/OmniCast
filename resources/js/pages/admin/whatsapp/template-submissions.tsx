import { Form, Head } from '@inertiajs/react';
import { Check, FileCheck2, X } from 'lucide-react';
import PageHeader from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { approve, reject } from '@/routes/admin/whatsapp/template-submissions';

type Submission = {
    id: number;
    client: string;
    connector: string;
    creator: string | null;
    name: string;
    language: string;
    category: string;
    components: { type: string; text?: string }[];
    submitted_at: string | null;
};

export default function WhatsAppTemplateSubmissions({ submissions }: { submissions: Submission[] }) {
    return (
        <>
            <Head title="WhatsApp template approvals" />
            <PageHeader icon={FileCheck2} title="WhatsApp template approvals" description="Review client-authored templates before provider submission." />
            <div className="mt-6 space-y-4">
                {submissions.length === 0 ? (
                    <section className="rounded-lg border bg-card p-6 text-sm text-muted-foreground">No WhatsApp templates are waiting for review.</section>
                ) : submissions.map((submission) => (
                    <section key={submission.id} className="rounded-lg border bg-card p-5">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <div className="flex flex-wrap items-center gap-2"><h2 className="text-lg font-semibold">{submission.name}</h2><Badge variant="muted">{submission.category}</Badge></div>
                                <p className="mt-1 text-sm text-muted-foreground">{submission.client} · {submission.connector} · {submission.language} · submitted by {submission.creator || 'client user'}</p>
                            </div>
                            <Form action={approve.url(submission.id)} method="post"><Button size="sm"><Check className="mr-1 size-4" />Approve copy</Button></Form>
                        </div>
                        <div className="mt-4 whitespace-pre-wrap rounded-md bg-muted p-3 text-sm">{submission.components.find((component) => component.type === 'BODY')?.text}</div>
                        <Form action={reject.url(submission.id)} method="post" className="mt-4 flex flex-col gap-2 sm:flex-row">
                            {({ errors, processing }) => <>
                                <input name="reason" required maxLength={255} placeholder="Reason for returning this template" className="h-10 flex-1 rounded-md border bg-background px-3 text-sm" />
                                {errors.reason && <span className="text-sm text-destructive">{errors.reason}</span>}
                                <Button type="submit" variant="outline" disabled={processing}><X className="mr-1 size-4" />Return to client</Button>
                            </>}
                        </Form>
                    </section>
                ))}
            </div>
        </>
    );
}
