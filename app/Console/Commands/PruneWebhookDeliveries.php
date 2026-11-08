<?php

namespace App\Console\Commands;

use App\Enums\WebhookDeliveryStatus;
use App\Models\WebhookDelivery;
use Illuminate\Console\Command;

/**
 * Deletes webhook deliveries past their retention window.
 *
 * A delivery that reached a final state is only kept long enough for support to
 * answer "did the callback go out?", so the log does not grow without bound on a
 * busy platform. An open delivery is never pruned: it still has a retry due.
 */
class PruneWebhookDeliveries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'webhooks:prune-deliveries
        {--days= : Override the configured retention window}
        {--dry-run : Report how many deliveries would be deleted without deleting them}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete finished webhook deliveries older than the retention window';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = max((int) ($this->option('days') ?: config('webhooks.retention_days', 30)), 1);
        $cutoff = now()->subDays($days);

        $query = WebhookDelivery::query()
            ->whereIn('status', [
                WebhookDeliveryStatus::Delivered->value,
                WebhookDeliveryStatus::Failed->value,
            ])
            ->where('created_at', '<', $cutoff);

        if ((bool) $this->option('dry-run')) {
            $this->info("{$query->count()} delivery row(s) older than {$days} day(s) would be deleted.");

            return self::SUCCESS;
        }

        $deleted = $query->delete();

        $this->info($deleted === 0
            ? 'No webhook delivery was old enough to prune.'
            : "Pruned {$deleted} webhook delivery row(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
