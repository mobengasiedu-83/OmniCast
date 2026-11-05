import { Head } from '@inertiajs/react';
import { FileSpreadsheet } from 'lucide-react';
import PageHeader from '@/components/page-header';
import ReportExportPanel from '@/components/report-export-panel';
import { urls } from '@/lib/urls';
import type { ClientOption, ReportExportRow, ReportOption } from '@/types';

type PageProps = {
    filters: { from: string; to: string };
    exports: ReportExportRow[];
    reports: ReportOption[];
    report: string;
    clients: ClientOption[];
    can: { export: boolean };
};

/**
 * The platform's report files: every export any operator or the scheduler
 * produced, with the client each one belongs to.
 */
export default function AdminReportExports({
    filters,
    exports,
    reports,
    report,
    clients,
    can,
}: PageProps) {
    return (
        <>
            <Head title="Report exports" />

            <div className="flex flex-col gap-4">
                <PageHeader
                    icon={FileSpreadsheet}
                    title="Report exports"
                    description="Every CSV generated on the platform, whether an operator asked for it or the scheduler did, grouped by the client it covers."
                />

                <ReportExportPanel
                    action={urls.admin.reports.exports.store}
                    exports={exports}
                    reports={reports}
                    defaults={filters}
                    clients={clients}
                    defaultReport={report}
                    canExport={can.export}
                    download={(row) =>
                        urls.admin.reports.exports.download(row.id)
                    }
                />
            </div>
        </>
    );
}

AdminReportExports.layout = {
    breadcrumbs: [
        {
            title: 'Report exports',
            href: urls.admin.reports.exports.index,
        },
    ],
};
