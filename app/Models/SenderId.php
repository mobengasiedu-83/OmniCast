<?php

namespace App\Models;

use App\Enums\SenderIdChannel;
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
 * @property SenderIdChannel $channel
 * @property string $name
 * @property string|null $country
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
    'client_id', 'channel', 'name', 'country', 'purpose', 'status', 'requested_at', 'submitted_at',
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
            'channel' => SenderIdChannel::class,
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
     * The team member that requested the sender ID.
     *
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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
        return $this->hasMany(Campaign::class, 'sender_id');
    }

    /**
     * The identity shown in listings, for example "OMNICAST · SMS".
     */
    public function displayName(): string
    {
        return "{$this->name} · {$this->channel->label()}";
    }

    /**
     * Whether the client may still change or withdraw the request.
     */
    public function isEditable(): bool
    {
        return in_array($this->status, [
            SenderIdStatus::Pending,
            SenderIdStatus::Submitted,
            SenderIdStatus::Rejected,
        ], true);
    }

    /**
     * Limit the query to a single delivery channel.
     */
    #[Scope]
    protected function forChannel(Builder $query, SenderIdChannel $channel): void
    {
        $query->where($query->qualifyColumn('channel'), $channel->value);
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
