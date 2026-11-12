<?php

namespace App\Jobs;

use App\Models\WhatsAppCampaignRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RetryWhatsAppRecipient implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $recipientId) {}

    public function handle(): void
    {
        $recipient = WhatsAppCampaignRecipient::query()->with('campaign')->find($this->recipientId);
        if ($recipient === null || $recipient->status !== 'retry_wait' || $recipient->next_attempt_at?->isFuture()) {
            return;
        }
        if ($recipient->attempts()->where('status', 'unknown')->exists()) {
            $recipient->forceFill(['status' => 'unknown'])->save();

            return;
        }
        if ($recipient->campaign?->control_state !== 'active') {
            return;
        }
        $recipient->forceFill(['status' => 'ready'])->save();
        SendWhatsAppBatch::dispatch((int) $recipient->campaign_id, [(int) $recipient->id])
            ->onQueue((string) config('queues.channels.whatsapp.queue', 'whatsapp'));
    }
}
