<?php

namespace App\Jobs;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Services\CampaignDispatcher;
use App\Services\Delivery\QueueTopology;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Runs one pass over a campaign audience on the queue that owns its channel.
 *
 * A pass renders and submits every outstanding recipient, so the job is given a
 * long timeout and an exponential backoff; when recipients are still waiting on
 * a retry the follow-up job is released on the same queue.
 */
class DispatchCampaignJob implements ShouldQueue
{
    use Queueable;

    /**
     * How many times the dispatcher may run before the job is failed.
     */
    public int $tries;

    /**
     * How long one pass may run before the worker kills it.
     */
    public int $timeout;

    /**
     * Fail the job rather than release it when it overruns its timeout.
     */
    public bool $failOnTimeout = true;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $campaignId)
    {
        $policy = app(QueueTopology::class)->dispatchPolicy();

        $this->tries = $policy['tries'];
        $this->timeout = $policy['timeout'];
    }

    /**
     * The exponential backoff between passes, in seconds.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return app(QueueTopology::class)->dispatchPolicy()['backoff'];
    }

    /**
     * The Horizon tags this job is grouped under.
     *
     * @return list<string>
     */
    public function tags(): array
    {
        return ['campaign:'.$this->campaignId];
    }

    /**
     * Hold a lock so two workers never dispatch the same campaign at once.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('campaign-dispatch-'.$this->campaignId))->expireAfter(900)];
    }

    /**
     * Execute the job.
     */
    public function handle(CampaignDispatcher $dispatcher, QueueTopology $queues): void
    {
        $campaign = Campaign::query()->with('client')->find($this->campaignId);

        if ($campaign === null) {
            return;
        }

        if ($campaign->status->isTerminal() || $campaign->status === CampaignStatus::Paused) {
            return;
        }

        if (! $campaign->client?->canBroadcast()) {
            if (! $campaign->status->isTerminal()) {
                $campaign->forceFill(['status' => CampaignStatus::Paused])->save();
            }

            return;
        }

        if ($dispatcher->run($campaign)) {
            $campaign->refresh();

            self::dispatch($campaign->id)
                ->onQueue($queues->queueForCampaign($campaign))
                ->delay(now()->addMinutes(max($campaign->retry_delay_minutes, 1)));
        }
    }
}
