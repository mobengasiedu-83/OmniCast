<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['client_id', 'market', 'category', 'volume_from', 'volume_to', 'currency', 'meta_rate', 'provider_fee', 'direct_cost', 'fx_rate', 'markup_percent', 'minimum_price', 'selling_price', 'status', 'source', 'effective_from', 'effective_to', 'created_by', 'approved_by', 'approved_at'])]
class WhatsAppPricingRate extends Model
{
    protected $table = 'whatsapp_pricing_rates';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['meta_rate' => 'decimal:6', 'provider_fee' => 'decimal:6', 'direct_cost' => 'decimal:6', 'fx_rate' => 'decimal:6', 'markup_percent' => 'decimal:4', 'minimum_price' => 'decimal:4', 'selling_price' => 'decimal:4', 'effective_from' => 'date', 'effective_to' => 'date', 'approved_at' => 'datetime'];
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function inputsSnapshot(): array
    {
        return [
            'rate_id' => $this->id, 'market' => $this->market, 'category' => $this->category,
            'volume_from' => $this->volume_from, 'volume_to' => $this->volume_to,
            'currency' => $this->currency, 'meta_rate' => (string) $this->meta_rate,
            'provider_fee' => (string) $this->provider_fee, 'direct_cost' => (string) $this->direct_cost,
            'fx_rate' => (string) $this->fx_rate, 'markup_percent' => (string) $this->markup_percent,
            'minimum_price' => (string) $this->minimum_price, 'selling_price' => (string) $this->selling_price,
            'effective_from' => $this->effective_from?->toDateString(),
        ];
    }
}
