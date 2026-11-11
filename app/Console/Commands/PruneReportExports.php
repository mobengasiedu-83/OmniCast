<?php

namespace App\Console\Commands;

use App\Enums\PrivacyRetentionCategory;
use App\Enums\ReportExportStatus;
use App\Models\ReportExport;
use App\Services\Privacy\PrivacyRetentionPolicyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Deletes report files that are past their retention window.
 *
 * Only a finished export is pruned: one still queued is left alone so a slow
 * worker can finish. The row is kept so the Exports screen can still say a file
 * was generated and has expired.
 */
class PruneReportExports extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reports:prune-exports
        {--days= : How long to keep a generated file (defaults to the configured window)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete generated report files that are past their retention window';

    /**
     * Execute the console command.
     */
    public function handle(PrivacyRetentionPolicyService $policies): int
    {
        $approvedDays = $policies->approvedDays(PrivacyRetentionCategory::ReportExports);
        if ($approvedDays === null) {
            $this->info('Report export pruning is disabled until a legally approved retention period is configured.');

            return self::SUCCESS;
        }

        $days = max(
            $approvedDays,
            (int) ($this->option('days') ?: config('reports.exports.retention_days', $approvedDays)),
        );
        $cutoff = now()->subDays($days);
        $disk = Storage::disk((string) config('reports.exports.disk', 'local'));
        $pruned = 0;

        ReportExport::query()
            ->whereNotNull('file_path')
            ->where('status', ReportExportStatus::Completed->value)
            ->whereNotNull('completed_at')
            ->where('completed_at', '<', $cutoff)
            ->orderBy('id')
            ->chunkById(100, function ($exports) use ($disk, $days, &$pruned): void {
                foreach ($exports as $export) {
                    if ($export->file_path !== null
                        && $disk->exists($export->file_path)
                        && ! $disk->delete($export->file_path)) {
                        throw new RuntimeException("The expired report file [{$export->file_path}] could not be deleted.");
                    }

                    $export->forceFill([
                        'file_path' => null,
                        'error_message' => "The file expired after {$days} days.",
                    ])->save();

                    $pruned++;
                }
            });

        $this->info("Pruned {$pruned} expired report file(s).");

        return self::SUCCESS;
    }
}
