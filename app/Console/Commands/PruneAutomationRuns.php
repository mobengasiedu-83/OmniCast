<?php

namespace App\Console\Commands;

use App\Enums\AutomationRunStatus;
use App\Models\AutomationRun;
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
    public function handle(): int
    {
        $days = max((int) ($this->option('days') ?: config('automations.retention_days', 90)), 1);
        $cutoff = now()->subDays($days);

        $query = AutomationRun::query()
            ->whereIn('status', [
                AutomationRunStatus::Completed->value,
                AutomationRunStatus::Failed->value,
                AutomationRunStatus::Cancelled->value,
            ])
            ->where('created_at', '<', $cutoff);

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
