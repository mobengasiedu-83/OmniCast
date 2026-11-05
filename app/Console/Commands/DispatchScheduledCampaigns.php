<?php

namespace App\Console\Commands;

use App\Enums\CampaignStatus;
use App\Jobs\DispatchCampaignJob;
use App\Models\Campaign;
use App\Services\Delivery\QueueTopology;
use Illuminate\Console\Command;

/**
 * Releases campaigns whose scheduled start time has arrived.
 *
 * A scheduled campaign is already sitting on the queue as a delayed job, so this
 * is the guarantee behind that job: if Redis was flushed, a worker was killed or
 * a deployment dropped the delayed set, the campaign is promoted and queued
 * again instead of waiting forever.
 */
class DispatchScheduledCampaigns extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'campaigns:dispatch-scheduled
        {--limit=100 : The maximum number of campaigns to release in one run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Queue every campaign whose scheduled start time has passed';

    /**
     * Execute the console command.
     */
    public function handle(QueueTopology $queues): int
    {
        $limit = max((int) $this->option('limit'), 1);
        $released = 0;

        Campaign::query()
            ->where('status', CampaignStatus::Scheduled->value)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->limit($limit)
            ->get()
            ->each(function (Campaign $campaign) use ($queues, &$released): void {
                $campaign->forceFill([
                    'status' => CampaignStatus::Running,
                    'launched_at' => $campaign->launched_at ?? now(),
                ])->save();

                DispatchCampaignJob::dispatch($campaign->id)
                    ->onQueue($queues->queueForCampaign($campaign));

                $released++;
            });

        $this->info($released === 0
            ? 'No scheduled campaigns were due.'
            : "Queued {$released} scheduled campaign(s).");

        return self::SUCCESS;
    }
}
