import { Head, router } from '@inertiajs/react';
import { Info } from 'lucide-react';
import PageHeader from '@/components/page-header';
import StatCard from '@/components/stat-card';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { urls } from '@/lib/urls';

type Requirement = 'registration' | 'campaign' | 'top_up';

type DocumentRow = {
    id: number;
    slug: string;
    title: string;
    summary: string | null;
    is_active: boolean;
    url: string;
    version: string | null;
    acceptances_count: number;
    requires: Record<Requirement, boolean>;
};

type PageProps = {
    documents: DocumentRow[];
    stats: {
        documents: number;
        registration: number;
        campaign: number;
        top_up: number;
    };
};

const REQUIREMENTS: Requirement[] = ['registration', 'campaign', 'top_up'];

/**
 * Where acceptance of each policy is collected.
 *
 * The three columns are the activities the platform can gate: an application, a
 * campaign submission and a wallet top-up. Flipping a switch changes the
 * platform's behaviour, never the policy text, so it takes effect on the next
 * visit to the affected screen.
 */
export default function ComplianceSettings({ documents, stats }: PageProps) {
    const toggle = (document: DocumentRow, field: Requirement): void => {
        const next = {
            ...document.requires,
            [field]: !document.requires[field],
        };

        router.patch(
            urls.admin.legal.documents.requirements(document.id),
            {
                requires_acceptance_at_registration: next.registration,
                requires_acceptance_on_campaign_submission: next.campaign,
                requires_acceptance_on_wallet_top_up: next.top_up,
            },
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Compliance settings" />

            <div className="grid gap-6">
                <PageHeader
                    title="Compliance settings"
                    description="Choose which policies a customer acknowledges on each activity."
                />

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard title="Documents" value={stats.documents} />
                    <StatCard
                        title="At application"
                        value={stats.registration}
                    />
                    <StatCard
                        title="At campaign submit"
                        value={stats.campaign}
                    />
                    <StatCard title="At wallet top-up" value={stats.top_up} />
                </div>

                <Card>
                    <CardContent className="grid gap-3">
                        <p className="flex items-start gap-2 rounded-lg border border-border/60 bg-muted/40 px-4 py-3 text-xs text-muted-foreground">
                            <Info className="mt-0.5 size-3.5 shrink-0" />
                            An acceptance already given stays valid for the
                            version it was given against. Removing a document
                            from an activity stops the question being asked from
                            then on; it does not delete the acceptances on
                            record.
                        </p>

                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Document</TableHead>
                                    <TableHead className="w-40">
                                        Application
                                    </TableHead>
                                    <TableHead className="w-40">
                                        Campaign submit
                                    </TableHead>
                                    <TableHead className="w-40">
                                        Wallet top-up
                                    </TableHead>
                                    <TableHead className="w-28">
                                        Accepted
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {documents.map((document) => (
                                    <TableRow key={document.id}>
                                        <TableCell className="font-medium">
                                            {document.title}
                                            <span className="flex items-center gap-1.5 text-xs font-normal text-muted-foreground">
                                                /legal/{document.slug}
                                                {document.version ? (
                                                    <Badge variant="info">
                                                        {document.version}
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="muted">
                                                        Not published
                                                    </Badge>
                                                )}
                                            </span>
                                        </TableCell>
                                        {REQUIREMENTS.map((field) => (
                                            <TableCell key={field}>
                                                <label className="flex items-center gap-2 text-sm">
                                                    <Checkbox
                                                        checked={
                                                            document.requires[
                                                                field
                                                            ]
                                                        }
                                                        onCheckedChange={() =>
                                                            toggle(
                                                                document,
                                                                field,
                                                            )
                                                        }
                                                    />
                                                    {document.requires[field]
                                                        ? 'Required'
                                                        : 'Not asked'}
                                                </label>
                                            </TableCell>
                                        ))}
                                        <TableCell>
                                            {document.acceptances_count}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>

                        {documents.length === 0 && (
                            <p className="p-8 text-center text-sm text-muted-foreground">
                                No policy documents exist yet. Create one from
                                Legal documents.
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

ComplianceSettings.layout = {
    breadcrumbs: [
        {
            title: 'Compliance settings',
            href: urls.admin.legal.complianceSettings,
        },
    ],
};
