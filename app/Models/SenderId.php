<?php

namespace App\Models;

use App\Enums\SenderIdStatus;
use Database\Factories\SenderIdFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $client_id
 * @property string $name
 * @property string $country
 * @property string|null $purpose
 * @property SenderIdStatus $status
 * @property Carbon|null $requested_at
 * @property Carbon|null $submitted_at
 * @property Carbon|null $approved_at
 * @property int|null $approved_by
 * @property Carbon|null $expires_at
 * @property string|null $rejection_reason
 */
#[Fillable([
    'client_id', 'name', 'country', 'purpose', 'status', 'requested_at', 'submitted_at',
    'approved_at', 'approved_by', 'expires_at', 'rejection_reason', 'created_by',
])]
class SenderId extends Model
{
    /** @use HasFactory<SenderIdFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SenderIdStatus::class,
            'requested_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'expires_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasMany<Campaign, $this>
     */
    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    /**
     * Limit the query to the client an authenticated user may see.
     */
    #[Scope]
    protected function forUser(Builder $query, User $user): void
    {
        if ($user->client_id !== null) {
            $query->where($query->qualifyColumn('client_id'), $user->client_id);
        }
    }
}
