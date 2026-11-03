<?php

namespace App\Models;

use App\Enums\CampaignStatus;
use App\Enums\Channel;
use App\Enums\SmsEncoding;
use Database\Factories\CampaignFactory;
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
 * @property string $reference
 * @property int $client_id
 * @property string $name
 * @property Channel $channel
 * @property CampaignStatus $status
 * @property int|null $contact_list_id
 * @property int|null $sender_id
 * @property int|null $caller_id
 * @property string|null $sms_message
 * @property SmsEncoding|null $sms_encoding
 * @property string|null $voice_script
 * @property string|null $voice_recording_path
 * @property int $voice_max_duration_seconds
 * @property int $estimated_recipients
 * @property int $estimated_sms_parts
 * @property string $estimated_cost
 * @property string $reserved_amount
 * @property string $actual_cost
 * @property string $schedule_type
 * @property Carbon|null $scheduled_at
 * @property string|null $window_starts_at
 * @property string|null $window_ends_at
 * @property string $timezone
 * @property int $max_retries
 * @property int $retry_delay_minutes
 * @property string|null $notes
 * @property Carbon|null $submitted_at
 * @property Carbon|null $approved_at
 * @property int|null $approved_by
 * @property Carbon|null $rejected_at
 * @property string|null $rejection_reason
 * @property Carbon|null $launched_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'reference', 'client_id', 'name', 'channel', 'status', 'contact_list_id', 'sender_id', 'caller_id',
    'sms_message', 'sms_encoding', 'voice_script', 'voice_recording_path', 'voice_max_duration_seconds',
    'estimated_recipients', 'estimated_sms_parts', 'estimated_cost',
    'reserved_amount', 'actual_cost', 'schedule_type', 'scheduled_at', 'window_starts_at',
    'window_ends_at', 'timezone', 'max_retries', 'retry_delay_minutes', 'notes',
    'submitted_at', 'approved_at', 'approved_by', 'rejected_at', 'rejection_reason',
    'launched_at', 'completed_at', 'created_by',
])]
class Campaign extends Model
{
    /** @use HasFactory<CampaignFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => Channel::class,
            'status' => CampaignStatus::class,
            'sms_encoding' => SmsEncoding::class,
            'estimated_recipients' => 'integer',
            'estimated_sms_parts' => 'integer',
            'voice_max_duration_seconds' => 'integer',
            'estimated_cost' => 'decimal:2',
            'reserved_amount' => 'decimal:2',
            'actual_cost' => 'decimal:2',
            'max_retries' => 'integer',
            'retry_delay_minutes' => 'integer',
            'scheduled_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'launched_at' => 'datetime',
            'completed_at' => 'datetime',
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
     * @return BelongsTo<ContactList, $this>
     */
    public function contactList(): BelongsTo
    {
        return $this->belongsTo(ContactList::class);
    }

    /**
     * @return BelongsTo<SenderId, $this>
     */
    public function senderId(): BelongsTo
    {
        return $this->belongsTo(SenderId::class);
    }

    /**
     * @return BelongsTo<CallerId, $this>
     */
    public function callerId(): BelongsTo
    {
        return $this->belongsTo(CallerId::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
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
     * @return HasMany<CampaignChannel, $this>
     */
    public function channels(): HasMany
    {
        return $this->hasMany(CampaignChannel::class);
    }

    /**
     * @return HasMany<CampaignContact, $this>
     */
    public function campaignContacts(): HasMany
    {
        return $this->hasMany(CampaignContact::class);
    }

    /**
     * @return HasMany<BillingReservation, $this>
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(BillingReservation::class);
    }

    /**
     * @return HasMany<WalletTransaction, $this>
     */
    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
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
     * Limit the query to campaigns waiting for an approval decision.
     */
    #[Scope]
    protected function awaitingApproval(Builder $query): void
    {
        $query->where('status', CampaignStatus::PendingApproval->value);
    }
}
