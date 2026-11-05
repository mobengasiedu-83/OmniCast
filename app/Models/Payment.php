<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A wallet top-up: what a client is paying, and what the gateway made of it.
 *
 * @property int $id
 * @property int $client_id
 * @property int|null $wallet_id
 * @property int|null $user_id
 * @property string $reference
 * @property string $provider
 * @property string|null $provider_reference
 * @property PaymentStatus $status
 * @property string $currency
 * @property string $amount
 * @property string $amount_paid
 * @property string|null $package_key
 * @property string $description
 * @property string|null $channel
 * @property string|null $authorization_url
 * @property string|null $access_code
 * @property string|null $failure_reason
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $paid_at
 * @property Carbon|null $created_at
 */
#[Fillable([
    'client_id', 'wallet_id', 'user_id', 'reference', 'provider', 'provider_reference',
    'status', 'currency', 'amount', 'amount_paid', 'package_key', 'description', 'channel',
    'authorization_url', 'access_code', 'failure_reason', 'metadata', 'paid_at',
])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'metadata' => 'array',
            'paid_at' => 'datetime',
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
     * @return BelongsTo<Wallet, $this>
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasOne<Invoice, $this>
     */
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    /**
     * @return HasMany<WalletTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    /**
     * Whether the gateway has already settled this payment.
     */
    public function isSettled(): bool
    {
        return $this->status->isSettled();
    }

    /**
     * The mobile money charge this payment is running, when it is one.
     *
     * @return array{phone?: string, provider?: string, provider_label?: string, status?: string, message?: string, requires_otp?: bool, updated_at?: string, checked_at?: string}|null
     */
    public function mobileMoney(): ?array
    {
        $mobileMoney = $this->metadata['mobile_money'] ?? null;

        return is_array($mobileMoney) ? $mobileMoney : null;
    }

    /**
     * Whether the payment is a charge prompted on a mobile money wallet.
     */
    public function isMobileMoney(): bool
    {
        return $this->mobileMoney() !== null;
    }

    /**
     * The wallet number the charge was sent to.
     */
    public function mobileMoneyNumber(): ?string
    {
        $mobileMoney = $this->mobileMoney();

        $phone = $mobileMoney['phone'] ?? null;

        return is_string($phone) ? $phone : null;
    }

    /**
     * The catalogue entry this payment was bought from, when it was a package.
     *
     * @return array{name: string, price: float, popular: bool, tagline: string, units: array<string, int>}|null
     */
    public function package(): ?array
    {
        if ($this->package_key === null) {
            return null;
        }

        /** @var array<string, array{name: string, price: float, popular: bool, tagline: string, units: array<string, int>}> $packages */
        $packages = config('billing.packages', []);

        return $packages[$this->package_key] ?? null;
    }
}
