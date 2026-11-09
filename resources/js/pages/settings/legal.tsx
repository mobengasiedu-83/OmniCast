import { Head, Link, router } from '@inertiajs/react';
import { CheckCircle2, FileText, Scale, ShieldCheck } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { badgeTone } from '@/lib/tone';
import { index as legalIndex } from '@/routes/legal';
import { accept as acceptRoute, dpa as requestDpa } from '@/routes/legal/settings';

type DocumentRow = {
    slug: string;
    title: string;
    summary: string | null;
    url: string;
    version: string | null;
    effective_on: string | null;
    accepted: boolean;
    accepted_at: string | null;
    accepted_by: string | null;
};

type HistoryRow = {
    id: number;
    document: string | null;
    version: string | null;
    context: string;
    context_label: string;
    accepted_at: string;
    accepted_by: string | null;
    ip_address: string | null;
};

type PageProps = {
    documents: DocumentRow[];
    outstanding: number;
    history: HistoryRow[];
    dpa: {
        status: string;
        status_label: string;
        tone: string;
        signatory_name: string | null;
        signed_at: string | null;
        requested_at: string | null;
        notes: string | null;
        url: string | null;
    };
};

/**
 * The organisation's policies, what it has accepted and its DPA position.
 *
 * The button records the live version of everything still outstanding — the
 * customer has just been shown each document it covers — and the history below
 * is read straight from the acceptance rows, so a teammate's acknowledgement on
 * the same organisation is visible here rather than looking like a gap.
 */
export default function LegalSettings({
    documents,
    outstanding,
    history,
    dpa,
}: PageProps) {
    return (
        <>
            <Head title="Legal & policies" />

            <Heading
                variant="small"
                title="Legal & policies"
                description="The documents that govern this organisation, the versions it has accepted and its data processing agreement."
                icon={Scale}
            />

            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <FileText className="size-4 text-brand" />
                        Policies
                        {outstanding > 0 && (
                            <Badge variant="warning">
                                {outstanding} awaiting acknowledgement
                            </Badge>
                        )}
                    </CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="divide-y divide-border/60">
                        {documents.map((document) => (
                            <div
                                key={document.slug}
                                className="flex flex-wrap items-start justify-between gap-3 py-3"
                            >
                                <div className="min-w-0">
                                    <p className="text-sm font-medium">
                                        {document.title}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {document.version ?? 'Not published'}
                                        {document.effective_on
                                            ? ` · effective ${document.effective_on}`
                                            : ''}
                                    </p>
                                    {document.accepted && (
                                        <p className="mt-1 flex items-center gap-1.5 text-xs text-muted-foreground">
                                            <CheckCircle2 className="size-3.5 text-emerald-500" />
                                            Acknowledged
                                            {document.accepted_by
                                                ? ` by ${document.accepted_by}`
                                                : ''}
                                            {document.accepted_at
                                                ? ` on ${new Date(document.accepted_at).toLocaleDateString()}`
                                                : ''}
                                        </p>
                                    )}
                                </div>
                                <div className="flex items-center gap-2">
                                    {document.accepted ? (
                                        <Badge variant="success">Accepted</Badge>
                                    ) : (
                                        <Badge variant="warning">
                                            Not yet accepted
                                        </Badge>
                                    )}
                                    <Button variant="outline" size="sm" asChild>
                                        <a
                                            href={document.url}
                                            target="_blank"
                                            rel="noreferrer"
                                        >
                                            Read
                                        </a>
                                    </Button>
                                </div>
                            </div>
                        ))}

                        {documents.length === 0 && (
                            <p className="py-6 text-center text-sm text-muted-foreground">
                                No policy has been published yet.
                            </p>
                        )}
                    </div>

                    {outstanding > 0 && (
                        <Button
                            type="button"
                            onClick={() =>
                                router.post(
                                    acceptRoute.url(),
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            Acknowledge the live versions
                        </Button>
                    )}
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <ShieldCheck className="size-4 text-brand" />
                        Data processing agreement
                        <Badge variant={badgeTone(dpa.tone)}>
                            {dpa.status_label}
                        </Badge>
                    </CardTitle>
                </CardHeader>
                <CardContent className="space-y-3 text-sm">
                    <p className="text-muted-foreground">
                        The agreement that covers the personal data OmniCast
                        processes on your behalf: contact imports, campaign
                        delivery and the delivery reports that come back.
                    </p>

                    {dpa.signed_at && (
                        <p className="text-xs text-muted-foreground">
                            Signed
                            {dpa.signatory_name
                                ? ` by ${dpa.signatory_name}`
                                : ''}{' '}
                            on {new Date(dpa.signed_at).toLocaleDateString()}.
                        </p>
                    )}

                    {!dpa.signed_at && dpa.requested_at && (
                        <p className="text-xs text-muted-foreground">
                            Requested on{' '}
                            {new Date(dpa.requested_at).toLocaleDateString()}.
                            The platform team will send the countersigned copy.
                        </p>
                    )}

                    {dpa.notes && (
                        <p className="rounded-lg bg-muted/50 p-3 text-xs text-muted-foreground">
                            {dpa.notes}
                        </p>
                    )}

                    <div className="flex flex-wrap gap-2">
                        {dpa.url && (
                            <Button variant="outline" size="sm" asChild>
                                <a
                                    href={dpa.url}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    Read the agreement
                                </a>
                            </Button>
                        )}
                        {dpa.status !== 'signed' && (
                            <Button
                                type="button"
                                size="sm"
                                onClick={() =>
                                    router.post(
                                        requestDpa.url(),
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                Request a signed copy
                            </Button>
                        )}
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Acceptance history</CardTitle>
                </CardHeader>
                <CardContent>
                    {history.length === 0 ? (
                        <p className="py-6 text-center text-sm text-muted-foreground">
                            No policy has been accepted by this organisation yet.
                        </p>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Document</TableHead>
                                    <TableHead>Version</TableHead>
                                    <TableHead>Where</TableHead>
                                    <TableHead>Account</TableHead>
                                    <TableHead>When</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {history.map((row) => (
                                    <TableRow key={row.id}>
                                        <TableCell>{row.document}</TableCell>
                                        <TableCell>{row.version}</TableCell>
                                        <TableCell>{row.context_label}</TableCell>
                                        <TableCell>
                                            {row.accepted_by ?? '—'}
                                        </TableCell>
                                        <TableCell className="text-xs whitespace-nowrap text-muted-foreground">
                                            {new Date(
                                                row.accepted_at,
                                            ).toLocaleString()}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>

            <p className="text-xs text-muted-foreground">
                Every document is also{' '}
                <Link
                    className="underline underline-offset-2"
                    href={legalIndex()}
                >
                    published publicly
                </Link>
                , so anyone can read it without signing in.
            </p>
        </>
    );
}
