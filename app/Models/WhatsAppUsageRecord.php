<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['provider_id', 'recipient_id', 'provider_event_id', 'invoice_id', 'provider_usage_id', 'provider_message_id', 'category', 'market', 'quantity', 'amount', 'internal_cost', 'currency', 'used_at', 'evidence'])]
class WhatsAppUsageRecord extends Model
{
    protected $table = 'whatsapp_usage_records';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['quantity' => 'integer', 'amount' => 'decimal:4', 'internal_cost' => 'decimal:4', 'used_at' => 'datetime', 'evidence' => 'array'];
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

    /** @return BelongsTo<WhatsAppProviderInvoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(WhatsAppProviderInvoice::class, 'invoice_id');
    }
}
