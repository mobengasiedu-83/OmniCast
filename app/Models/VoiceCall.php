<?php

namespace App\Models;

use App\Enums\VoiceCallStatus;
use Database\Factories\VoiceCallFactory;
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
 * @property string|null $provider_call_id
 * @property string $to_number
 * @property string $from_number
 * @property VoiceCallStatus $status
 * @property string|null $ivr_action
 * @property string|null $dtmf_digits
 * @property string|null $hangup_cause
 * @property int $attempts
 * @property Carbon|null $dialled_at
 * @property Carbon|null $ringing_at
 * @property Carbon|null $answered_at
 * @property Carbon|null $ended_at
 * @property int $duration_seconds
 * @property string $cost
 * @property string|null $error_message
 */
#[Fillable([
    'reference', 'client_id', 'campaign_id', 'campaign_contact_id', 'provider', 'provider_call_id',
    'to_number', 'from_number', 'status', 'ivr_action', 'dtmf_digits', 'hangup_cause', 'attempts',
    'dialled_at', 'ringing_at', 'answered_at', 'ended_at', 'duration_seconds', 'cost',
    'error_message', 'last_event',
])]
class VoiceCall extends Model
{
    /** @use HasFactory<VoiceCallFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => VoiceCallStatus::class,
            'attempts' => 'integer',
            'dialled_at' => 'datetime',
            'ringing_at' => 'datetime',
            'answered_at' => 'datetime',
            'ended_at' => 'datetime',
            'duration_seconds' => 'integer',
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
     * @return HasMany<VoiceCallEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(VoiceCallEvent::class);
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

    /**
     * Limit the query to calls that reached a human or a machine.
     */
    #[Scope]
    protected function answered(Builder $query): void
    {
        $query->whereIn($query->qualifyColumn('status'), [
            VoiceCallStatus::Answered->value,
            VoiceCallStatus::Completed->value,
        ]);
    }
}
