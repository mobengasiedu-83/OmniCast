<?php

namespace App\Jobs;

use App\Models\WhatsAppCampaign;
use App\Services\WhatsApp\WhatsAppDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendWhatsAppBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @param list<int> $recipientIds */
    public function __construct(public int $campaignId, public array $recipientIds) {}

    public function handle(WhatsAppDispatcher $dispatcher): void
    {
        if ($this->recipientIds === []) {
            $dispatcher->dispatchNextBatch(WhatsAppCampaign::query()->findOrFail($this->campaignId));

            return;
        }
        $dispatcher->sendBatch($this->campaignId, $this->recipientIds);
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addHours(12)->toDateTime();
    }
}
