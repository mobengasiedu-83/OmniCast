<?php

namespace App\Console\Commands;

use App\Enums\PrivacyRetentionCategory;
use App\Services\Privacy\PrivacyRetentionPolicyService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

#[Signature('privacy:prune-queue-artifacts')]
#[Description('Prune queue artifacts only when legal-approved retention windows exist')]
class PruneQueueArtifacts extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(PrivacyRetentionPolicyService $policies): int
    {
        $failedJobDays = $policies->approvedDays(PrivacyRetentionCategory::FailedJobs);
        if ($failedJobDays === null) {
            $this->line('Skipped failed jobs: no legally approved retention period is configured.');
        } else {
            $exitCode = Artisan::call('queue:prune-failed', [
                '--hours' => max(
                    $failedJobDays * 24,
                    (int) config('queues.failed.prune_after_hours', $failedJobDays * 24),
                ),
            ]);

            if ($exitCode !== self::SUCCESS) {
                return $exitCode;
            }

            $this->line(Artisan::output());
        }

        $batchDays = $policies->approvedDays(PrivacyRetentionCategory::JobBatches);
        if ($batchDays === null) {
            $this->line('Skipped completed queue batches: no legally approved retention period is configured.');
        } else {
            $exitCode = Artisan::call('queue:prune-batches', [
                '--hours' => max($batchDays * 24, 48),
            ]);

            if ($exitCode !== self::SUCCESS) {
                return $exitCode;
            }

            $this->line(Artisan::output());
        }

        return self::SUCCESS;
    }
}
