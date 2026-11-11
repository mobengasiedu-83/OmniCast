<?php

use App\Enums\PrivacyRetentionCategory;
use App\Models\PrivacyRetentionPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Add a failed job old enough to be eligible for retention pruning.
 */
function createFailedJob(int $daysOld): void
{
    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(),
        'connection' => 'database',
        'queue' => 'default',
        'payload' => '{}',
        'exception' => 'failed',
        'failed_at' => now()->subDays($daysOld),
    ]);
}

/**
 * Add a completed batch with timestamps old enough to be eligible for pruning.
 */
function createCompletedBatch(string $id, int $daysOld): void
{
    $finishedAt = now()->subDays($daysOld)->timestamp;

    DB::table('job_batches')->insert([
        'id' => $id,
        'name' => 'retention test',
        'total_jobs' => 1,
        'pending_jobs' => 0,
        'failed_jobs' => 0,
        'failed_job_ids' => '[]',
        'options' => null,
        'cancelled_at' => null,
        'created_at' => $finishedAt,
        'finished_at' => $finishedAt,
    ]);
}

it('preserves queue artifacts until legal retention periods are approved', function (): void {
    createFailedJob(60);
    createCompletedBatch('unapproved-batch', 60);

    $this->artisan('privacy:prune-queue-artifacts')
        ->expectsOutputToContain('Skipped failed jobs')
        ->expectsOutputToContain('Skipped completed queue batches')
        ->assertSuccessful();

    expect(DB::table('failed_jobs')->count())->toBe(1)
        ->and(DB::table('job_batches')->count())->toBe(1);
});

it('prunes only queue artifacts older than approved periods', function (): void {
    $approver = platformUser();

    foreach ([PrivacyRetentionCategory::FailedJobs, PrivacyRetentionCategory::JobBatches] as $category) {
        PrivacyRetentionPolicy::query()->create([
            'category' => $category,
            'retention_days' => 30,
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);
    }

    config()->set('queues.failed.prune_after_hours', 24);

    createFailedJob(45);
    createFailedJob(10);
    createCompletedBatch('expired-batch', 45);
    createCompletedBatch('recent-batch', 10);

    $this->artisan('privacy:prune-queue-artifacts')->assertSuccessful();

    expect(DB::table('failed_jobs')->count())->toBe(1)
        ->and(DB::table('job_batches')->pluck('id')->all())->toBe(['recent-batch']);
});
