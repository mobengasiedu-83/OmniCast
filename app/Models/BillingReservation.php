<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use Database\Factories\BillingReservationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $campaign_id
 * @property int $wallet_id
 * @property string $amount
 * @property string $captured_amount
 * @property string $released_amount
 * @property ReservationStatus $status
 */
#[Fillable(['campaign_id', 'wallet_id', 'amount', 'captured_amount', 'released_amount', 'status'])]
class BillingReservation extends Model
{
    /** @use HasFactory<BillingReservationFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ReservationStatus::class,
            'amount' => 'decimal:2',
            'captured_amount' => 'decimal:2',
            'released_amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * @return BelongsTo<Wallet, $this>
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * @return HasMany<WalletTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    /**
     * The amount still held on behalf of the campaign.
     */
    public function heldAmount(): float
    {
        return round((float) $this->amount - (float) $this->captured_amount - (float) $this->released_amount, 2);
    }
}
