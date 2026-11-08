<?php

namespace App\Models;

use App\Enums\SmppMessageStatus;
use App\Enums\SmsEncoding;
use Database\Factories\SmppMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A message an ESME submitted over SMPP, from the bind to the final receipt.
 *
 * `reference` is the OmniCast reference handed to the SMS transport, so the
 * signed `/webhooks/sms` receipt finds this row when it finds no campaign
 * message, and `message_id` is the id returned in `submit_sm_resp`.
 *
 * @property int $id
 * @property int $client_id
 * @property int|null $smpp_account_id
 * @property int|null $smpp_session_id
 * @property string $reference
 * @property string $message_id
 * @property string $source_address
 * @property string $destination_address
 * @property string $body
 * @property SmsEncoding $encoding
 * @property int $segments
 * @property SmppMessageStatus $status
 * @property string|null $provider
 * @property string|null $provider_message_id
 * @property string|null $error_message
 * @property Carbon|null $submitted_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $failed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'client_id', 'smpp_account_id', 'smpp_session_id', 'reference', 'message_id',
    'source_address', 'destination_address', 'body', 'encoding', 'segments',
    'status', 'provider', 'provider_message_id', 'error_message', 'submitted_at',
    'delivered_at', 'failed_at',
])]
class SmppMessage extends Model
{
    /** @use HasFactory<SmppMessageFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'encoding' => SmsEncoding::class,
            'status' => SmppMessageStatus::class,
            'segments' => 'integer',
            'submitted_at' => 'datetime',
            'delivered_at' => 'datetime',
            'failed_at' => 'datetime',
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
     * @return BelongsTo<SmppAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(SmppAccount::class, 'smpp_account_id');
    }

    /**
     * @return BelongsTo<SmppSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(SmppSession::class, 'smpp_session_id');
    }

    /**
     * @return HasMany<SmppDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(SmppDelivery::class);
    }
}
