<?php

namespace App\Models;

use Database\Factories\WalletFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $client_id
 * @property string $currency
 * @property string $balance
 * @property string $reserved_balance
 * @property string $low_balance_threshold
 */
#[Fillable(['client_id', 'currency', 'balance', 'reserved_balance', 'low_balance_threshold'])]
class Wallet extends Model
{
    /** @use HasFactory<WalletFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'balance' => 'decimal:4',
            'reserved_balance' => 'decimal:4',
            'low_balance_threshold' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return HasMany<WalletTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    /**
     * @return HasMany<BillingReservation, $this>
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(BillingReservation::class);
    }

    /**
     * The balance that is not already committed to an in-flight campaign.
     */
    public function availableBalance(): float
    {
        return round((float) $this->balance - (float) $this->reserved_balance, 4);
    }

    /**
     * Determine whether the wallet can cover the given amount.
     */
    public function canCover(float $amount): bool
    {
        return $this->availableBalance() >= round($amount, 4);
    }

    /**
     * Determine whether the wallet has fallen to or below its low balance threshold.
     */
    public function isLow(): bool
    {
        return (float) $this->low_balance_threshold > 0
            && $this->availableBalance() <= (float) $this->low_balance_threshold;
    }

    /**
     * Limit the query to the wallet of the client an authenticated user belongs to.
     */
    #[Scope]
    protected function forUser(Builder $query, User $user): void
    {
        if ($user->client_id !== null) {
            $query->where($query->qualifyColumn('client_id'), $user->client_id);
        }
    }
}
