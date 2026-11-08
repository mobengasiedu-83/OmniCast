<?php

namespace App\Jobs;

use App\Enums\AutomationRunStatus;
use App\Models\AutomationRun;
use App\Services\Automation\AutomationRunner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Picks a run up from where it stopped and works it forward.
 *
 * The job is idempotent by design: it reads the run's position from the database,
 * so a retry after a timeout re-enters the same step the worker was on rather than
 * the one it started with. A run that is already finished is a no-op.
 */
class RunAutomationStep implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $runId) {}

    /**
     * How many times a step is retried before the run is failed.
     */
    public function tries(): int
    {
        return max((int) config('automations.job.tries', 3), 1);
    }

    /**
     * The delay between attempts, in seconds.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return array_values(array_map('intval', (array) config('automations.job.backoff', [60, 300])));
    }

    /**
     * The number of seconds one slice of the run may take.
     */
    public function timeout(): int
    {
        return max((int) config('automations.job.timeout', 120), 1);
    }

    /**
     * Work the run forward.
     */
    public function handle(AutomationRunner $runner): void
    {
        $run = AutomationRun::query()->find($this->runId);

        if ($run === null || ! $run->status->isOpen()) {
            return;
        }

        $runner->advance($run);
    }

    /**
     * Stop a run the queue gave up on, so it does not look stuck in the history.
     */
    public function failed(?Throwable $exception): void
    {
        $run = AutomationRun::query()->find($this->runId);

        if ($run === null || ! $run->status->isOpen()) {
            return;
        }

        $run->forceFill([
            'status' => AutomationRunStatus::Failed,
            'detail' => mb_substr(
                'The queue gave up on the run: '.($exception?->getMessage() ?? 'unknown error'),
                0,
                250,
            ),
            'resume_at' => null,
            'finished_at' => now(),
        ])->save();
    }
}
