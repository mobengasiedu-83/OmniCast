<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['invoice_id', 'usage_record_id', 'provider_reference', 'provider_amount', 'internal_amount', 'variance', 'status', 'details', 'review_note', 'reviewed_at', 'reviewed_by'])]
class WhatsAppInvoiceReconciliation extends Model
{
    protected $table = 'whatsapp_invoice_reconciliations';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['provider_amount' => 'decimal:4', 'internal_amount' => 'decimal:4', 'variance' => 'decimal:4', 'details' => 'array', 'reviewed_at' => 'datetime'];
    }

    /** @return BelongsTo<WhatsAppProviderInvoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(WhatsAppProviderInvoice::class, 'invoice_id');
    }

    /** @return BelongsTo<WhatsAppUsageRecord, $this> */
    public function usageRecord(): BelongsTo
    {
        return $this->belongsTo(WhatsAppUsageRecord::class, 'usage_record_id');
    }
}
