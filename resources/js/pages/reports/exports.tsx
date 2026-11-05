import { Head } from '@inertiajs/react';
import { FileSpreadsheet } from 'lucide-react';
import PageHeader from '@/components/page-header';
import ReportExportPanel from '@/components/report-export-panel';
import { urls } from '@/lib/urls';
import type { ReportExportRow, ReportOption } from '@/types';

type PageProps = {
    filters: { from: string; to: string };
    exports: ReportExportRow[];
    reports: ReportOption[];
    report: string;
    can: { export: boolean };
};

/**
 * The tenant's report files: ask for a CSV and download it once the queue has
 * written it.
 */
export default function ReportExports({
    filters,
    exports,
    reports,
    report,
    can,
}: PageProps) {
    return (
        <>
            <Head title="Report exports" />

            <div className="flex flex-col gap-4">
                <PageHeader
                    icon={FileSpreadsheet}
                    title="Report exports"
                    description="Every CSV this account generated. Files are written in the background and pruned after the retention window."
                />

                <ReportExportPanel
                    action={urls.reports.exports.store}
                    exports={exports}
                    reports={reports}
                    defaults={filters}
                    defaultReport={report}
                    canExport={can.export}
                    download={(row) => urls.reports.exports.download(row.id)}
                />
            </div>
        </>
    );
}

ReportExports.layout = {
    breadcrumbs: [
        {
            title: 'Report exports',
            href: urls.reports.exports.index,
        },
    ],
};
