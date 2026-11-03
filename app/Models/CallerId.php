<?php

namespace App\Models;

use App\Enums\SenderIdStatus;
use Database\Factories\CallerIdFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $client_id
 * @property string $number
 * @property string|null $label
 * @property string|null $sip_trunk
 * @property SenderIdStatus $status
 * @property Carbon|null $approved_at
 * @property string|null $rejection_reason
 */
#[Fillable([
    'client_id', 'number', 'label', 'sip_trunk', 'status', 'requested_at',
    'approved_at', 'approved_by', 'rejection_reason', 'created_by',
])]
class CallerId extends Model
{
    /** @use HasFactory<CallerIdFactory> */
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
            'approved_at' => 'datetime',
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
