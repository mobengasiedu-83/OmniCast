<?php

namespace App\Models;

use App\Enums\EmailFormat;
use App\Enums\EmailMessageStatus;
use Database\Factories\EmailMessageFactory;
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
 * @property string $to_address
 * @property string $from_address
 * @property string|null $from_name
 * @property string $subject
 * @property string $body
 * @property EmailFormat $format
 * @property EmailMessageStatus $status
 * @property string|null $provider_status
 * @property int $attempts
 * @property Carbon|null $submitted_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $bounced_at
 * @property Carbon|null $failed_at
 * @property string $cost
 * @property string|null $error_message
 */
#[Fillable([
    'reference', 'client_id', 'campaign_id', 'campaign_contact_id', 'provider', 'provider_message_id',
    'to_address', 'from_address', 'from_name', 'subject', 'body', 'format', 'status', 'provider_status',
    'attempts', 'submitted_at', 'delivered_at', 'bounced_at', 'failed_at', 'cost', 'error_message',
    'last_event',
])]
class EmailMessage extends Model
{
    /** @use HasFactory<EmailMessageFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'format' => EmailFormat::class,
            'status' => EmailMessageStatus::class,
            'attempts' => 'integer',
            'submitted_at' => 'datetime',
            'delivered_at' => 'datetime',
            'bounced_at' => 'datetime',
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
     * @return HasMany<EmailMessageEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(EmailMessageEvent::class);
    }

    /**
     * Limit the query to the client an authenticated user may see.
     *
     * @param  Builder<EmailMessage>  $query
     */
    #[Scope]
    protected function forUser(Builder $query, User $user): void
    {
        if ($user->client_id !== null) {
            $query->where($query->qualifyColumn('client_id'), $user->client_id);
        }
    }

    /**
     * Limit the query to messages the mailbox confirmed.
     *
     * @param  Builder<EmailMessage>  $query
     */
    #[Scope]
    protected function delivered(Builder $query): void
    {
        $query->where($query->qualifyColumn('status'), EmailMessageStatus::Delivered->value);
    }
}
