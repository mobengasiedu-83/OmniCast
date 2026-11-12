<?php

namespace App\Services\WhatsApp;

use App\Jobs\SendWhatsAppBatch;
use App\Models\User;
use App\Models\WhatsAppCampaign;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WhatsAppCampaignControl
{
    public function __construct(private WhatsAppAuditLogger $audit) {}

    public function pause(WhatsAppCampaign $campaign, User $actor): WhatsAppCampaign
    {
        if (! in_array($campaign->control_state, ['active', 'paused'], true)) {
            throw ValidationException::withMessages(['campaign' => 'Only an active WhatsApp campaign can be paused.']);
        }

        return $this->change($campaign, $actor, 'paused', 'paused');
    }

    public function resume(WhatsAppCampaign $campaign, User $actor): WhatsAppCampaign
    {
        if ($campaign->control_state !== 'paused') {
            throw ValidationException::withMessages(['campaign' => 'Only a paused WhatsApp campaign can be resumed.']);
        }
        $campaign = $this->change($campaign, $actor, 'active', 'queued');
        SendWhatsAppBatch::dispatch((int) $campaign->id, [])->onQueue((string) config('queues.channels.whatsapp.queue', 'whatsapp'));

        return $campaign;
    }

    public function cancel(WhatsAppCampaign $campaign, User $actor): WhatsAppCampaign
    {
        if (in_array($campaign->control_state, ['cancelled', 'completed'], true)) {
            throw ValidationException::withMessages(['campaign' => 'This WhatsApp campaign can no longer be cancelled.']);
        }
        $campaign = $this->change($campaign, $actor, 'cancelled', 'cancelled');
        $campaign->recipients()->whereIn('status', ['pending', 'ready', 'retry_wait'])->update(['status' => 'cancelled']);

        return $campaign;
    }

    private function change(WhatsAppCampaign $campaign, User $actor, string $control, string $status): WhatsAppCampaign
    {
        return DB::transaction(function () use ($campaign, $actor, $control, $status): WhatsAppCampaign {
            $locked = WhatsAppCampaign::query()->lockForUpdate()->findOrFail($campaign->id);
            if (in_array($locked->control_state, ['cancelled', 'completed'], true)) {
                throw ValidationException::withMessages(['campaign' => 'This WhatsApp campaign can no longer be changed.']);
            }
            $before = $locked->control_state;
            $locked->forceFill(['control_state' => $control, 'status' => $status])->save();
            $this->audit->record('campaign.'.$control, $actor, $actor->client, $locked, ['from' => $before, 'to' => $control]);

            return $locked;
        });
    }
}
