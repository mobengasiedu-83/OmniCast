<?php

namespace App\Models;

use App\Enums\BillingUnit;
use App\Enums\Channel;
use Database\Factories\RateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $rate_card_id
 * @property Channel $channel
 * @property string $destination
 * @property string|null $destination_label
 * @property BillingUnit $billing_unit
 * @property string $unit_price
 * @property string $supplier_cost
 */
#[Fillable([
    'rate_card_id', 'channel', 'destination', 'destination_label', 'billing_unit', 'unit_price', 'supplier_cost',
])]
class Rate extends Model
{
    /** @use HasFactory<RateFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => Channel::class,
            'billing_unit' => BillingUnit::class,
            'unit_price' => 'decimal:4',
            'supplier_cost' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<RateCard, $this>
     */
    public function rateCard(): BelongsTo
    {
        return $this->belongsTo(RateCard::class);
    }

    /**
     * The margin earned on every billed unit.
     */
    public function margin(): float
    {
        return round((float) $this->unit_price - (float) $this->supplier_cost, 4);
    }
}
