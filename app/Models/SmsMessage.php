<?php

namespace App\Models;

use App\Enums\SmsEncoding;
use App\Enums\SmsMessageStatus;
use Database\Factories\SmsMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $reference
 * @property int $client_id
 * @property int $campaign_id
 * @property int|null $campaign_contact_id
 * @property string $provider
 * @property string|null $provider_message_id
 * @property string $to_number
 * @property string $from_number
 * @property string $body
 * @property SmsEncoding $encoding
 * @property int $segments
 * @property SmsMessageStatus $status
 * @property string|null $provider_status
 * @property int $attempts
 * @property Carbon|null $submitted_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $failed_at
 * @property string $cost
 * @property string|null $error_message
 */
#[Fillable([
    'reference', 'client_id', 'campaign_id', 'campaign_contact_id', 'provider', 'provider_message_id',
    'to_number', 'from_number', 'body', 'encoding', 'segments', 'status', 'provider_status', 'attempts',
    'submitted_at', 'delivered_at', 'failed_at', 'cost', 'error_message', 'last_event',
])]
class SmsMessage extends Model
{
    /** @use HasFactory<SmsMessageFactory> */
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
            'status' => SmsMessageStatus::class,
            'segments' => 'integer',
            'attempts' => 'integer',
            'submitted_at' => 'datetime',
            'delivered_at' => 'datetime',
            'failed_at' => 'datetime',
            'cost' => 'decimal:4',
            'last_event' => 'array',
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
     * @return BelongsTo<CampaignContact, $this>
     */
    public function campaignContact(): BelongsTo
    {
        return $this->belongsTo(CampaignContact::class);
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return HasMany<SmsMessageEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(SmsMessageEvent::class);
    }

    /**
     * Limit the query to the client an authenticated user may see.
     *
     * @param  Builder<SmsMessage>  $query
     */
    #[Scope]
    protected function forUser(Builder $query, User $user): void
    {
        if ($user->client_id !== null) {
            $query->where($query->qualifyColumn('client_id'), $user->client_id);
        }
    }

    /**
     * Limit the query to messages the handset confirmed.
     *
     * @param  Builder<SmsMessage>  $query
     */
    #[Scope]
    protected function delivered(Builder $query): void
    {
        $query->where($query->qualifyColumn('status'), SmsMessageStatus::Delivered->value);
    }
}
