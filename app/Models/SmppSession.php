<?php

namespace App\Models;

use App\Enums\SmppBindMode;
use Database\Factories\SmppSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One bind the SMPP server accepted, kept for operations rather than billing.
 *
 * The socket itself lives in the daemon; the row is how `/admin/smpp` and the
 * audit trail know who was bound, from where, and for how long.
 *
 * @property int $id
 * @property int|null $smpp_account_id
 * @property string $system_id
 * @property SmppBindMode $mode
 * @property string|null $remote_address
 * @property Carbon $bound_at
 * @property Carbon|null $last_seen_at
 * @property Carbon|null $unbound_at
 * @property int $pdus_in
 * @property int $pdus_out
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'smpp_account_id', 'system_id', 'mode', 'remote_address', 'bound_at',
    'last_seen_at', 'unbound_at', 'pdus_in', 'pdus_out',
])]
class SmppSession extends Model
{
    /** @use HasFactory<SmppSessionFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mode' => SmppBindMode::class,
            'bound_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'unbound_at' => 'datetime',
            'pdus_in' => 'integer',
            'pdus_out' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<SmppAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(SmppAccount::class, 'smpp_account_id');
    }

    /**
     * @return HasMany<SmppMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(SmppMessage::class);
    }

    /**
     * Limit the query to sessions that are still bound.
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereNull('unbound_at');
    }

    /**
     * Mark the session closed.
     */
    public function close(): void
    {
        if ($this->unbound_at !== null) {
            return;
        }

        $this->forceFill([
            'unbound_at' => now(),
            'last_seen_at' => now(),
        ])->save();
    }
}
