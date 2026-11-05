<?php

namespace App\Console\Commands;

use App\Enums\CampaignContactStatus;
use App\Enums\CampaignStatus;
use App\Jobs\DispatchCampaignJob;
use App\Models\Campaign;
use App\Services\Delivery\QueueTopology;
use Illuminate\Console\Command;

/**
 * Re-queues campaigns that stopped making progress.
 *
 * A running campaign whose audience is still waiting and whose status has not
 * moved for longer than the retry window has lost its job: the worker died, the
 * queue was flushed or a deployment restarted Horizon mid-pass. The sweep puts it
 * back on its channel queue, where the dispatcher's overlap lock and its
 * per-recipient statuses make a duplicate pass harmless.
 */
class SweepStalledCampaigns extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'campaigns:sweep
        {--limit= : The maximum number of campaigns to re-queue in one run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Re-queue running campaigns that stopped making progress';

    /**
     * Execute the console command.
     */
    public function handle(QueueTopology $queues): int
    {
        $stalledAfter = max((int) config('queues.scheduler.stalled_after_minutes', 15), 1);
        $limit = max((int) ($this->option('limit') ?: config('queues.scheduler.sweep_limit', 50)), 1);
        $requeued = 0;

        $candidates = Campaign::query()
            ->where('status', CampaignStatus::Running->value)
            ->where('updated_at', '<=', now()->subMinutes($stalledAfter))
            ->whereHas('campaignContacts', fn ($query) => $query->whereIn('status', [
                CampaignContactStatus::Pending->value,
                CampaignContactStatus::Queued->value,
            ]))
            ->orderBy('updated_at')
            ->limit($limit)
            ->get();

        foreach ($candidates as $campaign) {
            // A retry pass is deliberately delayed, so the campaign only counts
            // as stalled once its own retry window has also elapsed.
            $quietFor = max($stalledAfter, $campaign->retry_delay_minutes + 5);

            if ($campaign->updated_at?->gt(now()->subMinutes($quietFor))) {
                continue;
            }

            DispatchCampaignJob::dispatch($campaign->id)
                ->onQueue($queues->queueForCampaign($campaign));

            $requeued++;
        }

        $this->info($requeued === 0
            ? 'No stalled campaigns were found.'
            : "Re-queued {$requeued} stalled campaign(s).");

        return self::SUCCESS;
    }
}
