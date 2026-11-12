<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['provider_id', 'recipient_id', 'provider_event_id', 'provider_message_id', 'event_type', 'status', 'occurred_at', 'signature_verified', 'billable', 'payload_redacted', 'processing_status', 'processed_at'])]
class WhatsAppProviderEvent extends Model
{
    protected $table = 'whatsapp_provider_events';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['occurred_at' => 'datetime', 'processed_at' => 'datetime', 'signature_verified' => 'boolean', 'billable' => 'boolean', 'payload_redacted' => 'array'];
    }

    /** @return BelongsTo<WhatsAppProvider, $this> */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(WhatsAppProvider::class, 'provider_id');
    }

    /** @return BelongsTo<WhatsAppCampaignRecipient, $this> */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(WhatsAppCampaignRecipient::class, 'recipient_id');
    }
}
