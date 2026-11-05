<?php

namespace App\Jobs;

use App\Models\ReportExport;
use App\Services\Reporting\ReportExportManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

/**
 * Writes one report file and marks the export ready.
 *
 * Exports share the default queue: they are slow enough to deserve a worker but
 * must never compete with the channel queues that deliver a campaign.
 */
class GenerateReportExport implements ShouldQueue
{
    use Queueable;

    /**
     * How many times the worker may try to write the file.
     */
    public int $tries = 3;

    /**
     * How long a large export may run.
     */
    public int $timeout = 300;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $exportId)
    {
        $this->onQueue('default');
    }

    /**
     * The exponential backoff between attempts, in seconds.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    /**
     * Keep two workers from writing the same export at once.
     *
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new WithoutOverlapping('report-export-'.$this->exportId)];
    }

    public function handle(ReportExportManager $manager): void
    {
        $export = ReportExport::query()->find($this->exportId);

        if ($export === null || $export->isReady()) {
            return;
        }

        $manager->generate($export);
    }

    /**
     * Record the failure so the requester sees why the file never arrived.
     */
    public function failed(?Throwable $exception): void
    {
        ReportExport::query()
            ->whereKey($this->exportId)
            ->update([
                'status' => 'failed',
                'error_message' => $exception?->getMessage(),
            ]);
    }
}
