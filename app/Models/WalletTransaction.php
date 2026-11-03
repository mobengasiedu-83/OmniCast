<?php

namespace App\Models;

use App\Enums\WalletTransactionType;
use Database\Factories\WalletTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $wallet_id
 * @property int|null $campaign_id
 * @property int|null $billing_reservation_id
 * @property WalletTransactionType $type
 * @property string $amount
 * @property string $balance_after
 * @property string $description
 * @property string|null $reference
 * @property array<string, mixed>|null $metadata
 */
#[Fillable([
    'wallet_id', 'campaign_id', 'billing_reservation_id', 'type', 'amount',
    'balance_after', 'description', 'reference', 'created_by', 'metadata',
])]
class WalletTransaction extends Model
{
    /** @use HasFactory<WalletTransactionFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => WalletTransactionType::class,
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Wallet, $this>
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * @return BelongsTo<BillingReservation, $this>
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(BillingReservation::class, 'billing_reservation_id');
    }
}
