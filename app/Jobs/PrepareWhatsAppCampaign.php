<?php

namespace App\Jobs;

use App\Models\WhatsAppCampaign;
use App\Services\WhatsApp\WhatsAppDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PrepareWhatsAppCampaign implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $campaignId, public bool $test = false) {}

    public function handle(WhatsAppDispatcher $dispatcher): void
    {
        $campaign = WhatsAppCampaign::query()->find($this->campaignId);
        if ($campaign !== null) {
            $dispatcher->prepare($campaign, $this->test);
        }
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addHours(2)->toDateTime();
    }
}
