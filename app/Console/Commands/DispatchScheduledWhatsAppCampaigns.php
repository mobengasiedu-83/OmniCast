<?php

namespace App\Console\Commands;

use App\Jobs\PrepareWhatsAppCampaign;
use App\Models\WhatsAppCampaign;
use App\Services\WhatsApp\WhatsAppDispatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DispatchScheduledWhatsAppCampaigns extends Command
{
    protected $signature = 'whatsapp:dispatch-scheduled';

    protected $description = 'Queue due WhatsApp campaign drafts for preparation.';

    public function handle(WhatsAppDispatcher $dispatcher): int
    {
        if (! config('whatsapp.sending_enabled', false)) {
            return self::SUCCESS;
        }
        WhatsAppCampaign::query()->where('status', 'scheduled')->where('control_state', 'active')
            ->whereNotNull('scheduled_at')->where('scheduled_at', '<=', now())->orderBy('id')->chunkById(100, function ($campaigns): void {
                foreach ($campaigns as $campaign) {
                    $claimed = DB::table('whatsapp_campaigns')->where('id', $campaign->id)->where('status', 'scheduled')->where('control_state', 'active')->update(['status' => 'preparing', 'dispatch_started_at' => now(), 'updated_at' => now()]);
                    if ($claimed === 1) {
                        PrepareWhatsAppCampaign::dispatch((int) $campaign->id)
                            ->onQueue((string) config('queues.channels.whatsapp.queue', 'whatsapp'));
                    }
                }
            });
        $dispatcher->dispatchDueRetries();

        return self::SUCCESS;
    }
}
