<?php

namespace App\Jobs;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Services\CampaignDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class DispatchCampaignJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $campaignId)
    {
        //
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
    public function handle(CampaignDispatcher $dispatcher): void
    {
        $campaign = Campaign::query()->find($this->campaignId);

        if ($campaign === null) {
            return;
        }

        if ($campaign->status->isTerminal() || $campaign->status === CampaignStatus::Paused) {
            return;
        }

        if ($dispatcher->run($campaign)) {
            $campaign->refresh();

            self::dispatch($campaign->id)
                ->delay(now()->addMinutes(max($campaign->retry_delay_minutes, 1)));
        }
    }
}
