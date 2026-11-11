<?php

namespace App\Console\Commands;

use App\Enums\AutomationRunStatus;
use App\Enums\PrivacyRetentionCategory;
use App\Models\AutomationRun;
use App\Services\Privacy\PrivacyRetentionPolicyService;
use Illuminate\Console\Command;

/**
 * Deletes automation runs past their retention window.
 *
 * A finished run is kept long enough for an operator to answer "did the
 * workflow text this contact, and when?", then dropped so the history table
 * does not grow without bound. A run that is still queued, running or waiting is
 * never pruned: a worker still has something to do with it.
 */
class PruneAutomationRuns extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'automations:prune-runs
        {--days= : Override the configured retention window}
        {--dry-run : Report how many runs would be deleted without deleting them}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete finished automation runs older than the retention window';

    /**
     * Execute the console command.
     */
    public function handle(PrivacyRetentionPolicyService $policies): int
    {
        $approvedDays = $policies->approvedDays(PrivacyRetentionCategory::AutomationRuns);
        if ($approvedDays === null) {
            $this->info('Automation run pruning is disabled until a legally approved retention period is configured.');

            return self::SUCCESS;
        }

        $days = max(
            $approvedDays,
            (int) ($this->option('days') ?: config('automations.retention_days', $approvedDays)),
        );
        $cutoff = now()->subDays($days);

        $query = AutomationRun::query()
            ->whereIn('status', [
                AutomationRunStatus::Completed->value,
                AutomationRunStatus::Failed->value,
                AutomationRunStatus::Cancelled->value,
            ])
            ->whereNotNull('finished_at')
            ->where('finished_at', '<', $cutoff);

        if ((bool) $this->option('dry-run')) {
            $this->info("{$query->count()} run row(s) older than {$days} day(s) would be deleted.");

            return self::SUCCESS;
        }

        $deleted = $query->delete();

        $this->info($deleted === 0
            ? 'No automation run was old enough to prune.'
            : "Pruned {$deleted} automation run row(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
