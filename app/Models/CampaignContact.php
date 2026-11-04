<?php

namespace App\Models;

use App\Enums\CampaignContactStatus;
use Database\Factories\CampaignContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $campaign_id
 * @property int $contact_id
 * @property CampaignContactStatus $status
 * @property int $attempts
 * @property Carbon|null $last_attempt_at
 * @property Carbon|null $sent_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $failed_at
 * @property string $cost
 */
#[Fillable([
    'campaign_id', 'contact_id', 'status', 'attempts', 'last_attempt_at',
    'sent_at', 'delivered_at', 'failed_at', 'cost', 'error_message',
    'recipient_snapshot',
])]
class CampaignContact extends Model
{
    /** @use HasFactory<CampaignContactFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CampaignContactStatus::class,
            'attempts' => 'integer',
            'last_attempt_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'failed_at' => 'datetime',
            'cost' => 'decimal:4',
            'recipient_snapshot' => 'array',
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
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * The calls dialled for this recipient, one row per attempt.
     *
     * @return HasMany<VoiceCall, $this>
     */
    public function voiceCalls(): HasMany
    {
        return $this->hasMany(VoiceCall::class);
    }
}
