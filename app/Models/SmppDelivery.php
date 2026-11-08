<?php

namespace App\Models;

use Database\Factories\SmppDeliveryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One `deliver_sm` the server still owes (or has sent to) an ESME.
 *
 * The receipt is queued as a row rather than written straight to a socket,
 * because the socket lives in the server process while the delivery receipt is
 * applied by a web request or a worker.
 *
 * @property int $id
 * @property int $smpp_message_id
 * @property int|null $smpp_account_id
 * @property string $system_id
 * @property string $stat
 * @property string|null $err
 * @property string|null $text
 * @property Carbon|null $sent_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'smpp_message_id', 'smpp_account_id', 'system_id', 'stat', 'err', 'text', 'sent_at',
])]
class SmppDelivery extends Model
{
    /** @use HasFactory<SmppDeliveryFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<SmppMessage, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(SmppMessage::class, 'smpp_message_id');
    }

    /**
     * Limit the query to receipts still waiting for their session.
     */
    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->whereNull('sent_at');
    }
}
