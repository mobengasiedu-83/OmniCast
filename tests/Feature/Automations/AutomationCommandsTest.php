<?php

use App\Enums\AutomationAction;
use App\Enums\AutomationRunStatus;
use App\Enums\AutomationTrigger;
use App\Enums\PrivacyRetentionCategory;
use App\Models\AutomationRun;
use App\Models\AutomationStep;
use App\Models\AutomationWorkflow;
use App\Models\PrivacyRetentionPolicy;
use Illuminate\Support\Facades\Bus;

beforeEach(function (): void {
    config()->set('automations.enabled', true);

    [$this->client, $this->wallet, $this->user] = clientWithWallet();
});

/**
 * A finished run created the given number of days ago.
 */
function runAgedDays(int $days, AutomationRunStatus $status = AutomationRunStatus::Completed): AutomationRun
{
    $run = AutomationRun::factory()->create(['status' => $status]);

    $run->forceFill([
        'created_at' => now()->subDays($days),
        'finished_at' => now()->subDays($days),
    ])->save();

    return $run;
}

it('prunes finished runs past the retention window', function (): void {
    config()->set('automations.retention_days', 90);
    PrivacyRetentionPolicy::query()->create([
        'category' => PrivacyRetentionCategory::AutomationRuns,
        'retention_days' => 90,
        'approved_by' => platformUser()->id,
        'approved_at' => now(),
    ]);

    $stale = runAgedDays(120);
    $staleFailed = runAgedDays(120, AutomationRunStatus::Failed);
    $fresh = runAgedDays(10);
    $open = runAgedDays(120, AutomationRunStatus::Waiting);

    $this->artisan('automations:prune-runs')->assertExitCode(0);

    $remaining = AutomationRun::query()->orderBy('id')->pluck('id')->all();

    expect($remaining)->toBe([$fresh->id, $open->id])
        ->and(AutomationRun::query()->whereKey($stale->id)->exists())->toBeFalse()
        ->and(AutomationRun::query()->whereKey($staleFailed->id)->exists())->toBeFalse();
});

it('reports what a dry run would delete without deleting anything', function (): void {
    PrivacyRetentionPolicy::query()->create([
        'category' => PrivacyRetentionCategory::AutomationRuns,
        'retention_days' => 90,
        'approved_by' => platformUser()->id,
        'approved_at' => now(),
    ]);

    runAgedDays(120);

    $this->artisan('automations:prune-runs', ['--dry-run' => true])->assertExitCode(0);

    expect(AutomationRun::query()->count())->toBe(1);
});

it('preserves finished runs until a retention period is legally approved', function (): void {
    $stale = runAgedDays(120);

    $this->artisan('automations:prune-runs')
        ->expectsOutputToContain('disabled until a legally approved retention period')
        ->assertExitCode(0);

    expect(AutomationRun::query()->whereKey($stale->id)->exists())->toBeTrue();
});

it('does not let configuration or command options shorten the approved period', function (): void {
    config()->set('automations.retention_days', 7);
    PrivacyRetentionPolicy::query()->create([
        'category' => PrivacyRetentionCategory::AutomationRuns,
        'retention_days' => 90,
        'approved_by' => platformUser()->id,
        'approved_at' => now(),
    ]);
    $withinApprovedPeriod = runAgedDays(60);

    $this->artisan('automations:prune-runs', ['--days' => 1])->assertExitCode(0);

    expect(AutomationRun::query()->whereKey($withinApprovedPeriod->id)->exists())->toBeTrue();
});

it('starts only the scheduled workflow whose hour has arrived', function (): void {
    Bus::fake();

    $due = AutomationWorkflow::factory()->for($this->client)
        ->triggeredBy(AutomationTrigger::Schedule, ['hour' => 4])
        ->create();

    AutomationStep::factory()
        ->action(AutomationAction::Stop)
        ->for($due, 'workflow')
        ->create();

    $later = AutomationWorkflow::factory()->for($this->client)
        ->triggeredBy(AutomationTrigger::Schedule, ['hour' => 5])
        ->create();

    AutomationStep::factory()
        ->action(AutomationAction::Stop)
        ->for($later, 'workflow')
        ->create();

    $this->artisan('automations:run-scheduled', ['--hour' => 4])->assertExitCode(0);

    expect(AutomationRun::query()->where('automation_workflow_id', $due->id)->count())->toBe(1)
        ->and(AutomationRun::query()->where('automation_workflow_id', $later->id)->count())->toBe(0);
});
