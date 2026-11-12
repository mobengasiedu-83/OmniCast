<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['provider_id', 'invoice_number', 'period_start', 'period_end', 'currency', 'total_amount', 'status', 'source_metadata', 'imported_by'])]
class WhatsAppProviderInvoice extends Model
{
    protected $table = 'whatsapp_provider_invoices';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'total_amount' => 'decimal:4', 'source_metadata' => 'array'];
    }

    /** @return BelongsTo<WhatsAppProvider, $this> */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(WhatsAppProvider::class, 'provider_id');
    }

    /** @return HasMany<WhatsAppUsageRecord, $this> */
    public function usageRecords(): HasMany
    {
        return $this->hasMany(WhatsAppUsageRecord::class, 'invoice_id');
    }

    /** @return HasMany<WhatsAppInvoiceReconciliation, $this> */
    public function reconciliations(): HasMany
    {
        return $this->hasMany(WhatsAppInvoiceReconciliation::class, 'invoice_id');
    }
}
