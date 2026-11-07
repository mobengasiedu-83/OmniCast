<?php

namespace App\Models;

use App\Enums\CampaignAudioStatus;
use App\Enums\CampaignStatus;
use App\Enums\Channel;
use App\Enums\EmailFormat;
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
 * @property string|null $idempotency_key
 * @property int $client_id
 * @property int|null $duplicated_from_id
 * @property string $name
 * @property Channel $channel
 * @property CampaignStatus $status
 * @property int $current_version
 * @property int|null $contact_list_id
 * @property int|null $contact_segment_id
 * @property int|null $sender_id
 * @property int|null $caller_id
 * @property int|null $message_template_id
 * @property string|null $sms_message
 * @property SmsEncoding|null $sms_encoding
 * @property string|null $email_subject
 * @property string|null $email_body
 * @property EmailFormat $email_format
 * @property string|null $voice_script
 * @property string|null $voice_recording_path
 * @property int $voice_max_duration_seconds
 * @property bool $ivr_enabled
 * @property string|null $ivr_prompt
 * @property int $ivr_timeout_seconds
 * @property int $ivr_max_attempts
 * @property array<string, string>|null $ivr_options
 * @property CampaignAudioStatus $audio_status
 * @property Carbon|null $audio_reviewed_at
 * @property int|null $audio_reviewed_by
 * @property string|null $audio_rejection_reason
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
    'reference', 'idempotency_key', 'client_id', 'duplicated_from_id', 'name', 'channel', 'status', 'current_version',
    'contact_list_id', 'contact_segment_id', 'sender_id', 'caller_id', 'message_template_id',
    'sms_message', 'sms_encoding', 'email_subject', 'email_body', 'email_format',
    'voice_script', 'voice_recording_path', 'voice_max_duration_seconds',
    'ivr_enabled', 'ivr_prompt', 'ivr_timeout_seconds', 'ivr_max_attempts', 'ivr_options',
    'audio_status', 'audio_reviewed_at', 'audio_reviewed_by', 'audio_rejection_reason',
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
            'audio_status' => CampaignAudioStatus::class,
            'sms_encoding' => SmsEncoding::class,
            'email_format' => EmailFormat::class,
            'current_version' => 'integer',
            'estimated_recipients' => 'integer',
            'estimated_sms_parts' => 'integer',
            'voice_max_duration_seconds' => 'integer',
            'ivr_enabled' => 'boolean',
            'ivr_timeout_seconds' => 'integer',
            'ivr_max_attempts' => 'integer',
            'ivr_options' => 'array',
            'estimated_cost' => 'decimal:4',
            'reserved_amount' => 'decimal:4',
            'actual_cost' => 'decimal:4',
            'max_retries' => 'integer',
            'retry_delay_minutes' => 'integer',
            'scheduled_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'audio_reviewed_at' => 'datetime',
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

    /** @return BelongsTo<ContactSegment, $this> */
    public function contactSegment(): BelongsTo
    {
        return $this->belongsTo(ContactSegment::class);
    }

    /**
     * @return BelongsTo<SenderId, $this>
     */
    public function senderId(): BelongsTo
    {
        return $this->belongsTo(SenderId::class, 'sender_id');
    }

    /**
     * @return BelongsTo<CallerId, $this>
     */
    public function callerId(): BelongsTo
    {
        return $this->belongsTo(CallerId::class, 'caller_id');
    }

    /**
     * The library template the copy started from, if any.
     *
     * @return BelongsTo<MessageTemplate, $this>
     */
    public function messageTemplate(): BelongsTo
    {
        return $this->belongsTo(MessageTemplate::class, 'message_template_id');
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
     * The reviewer that approved or rejected the voice audio.
     *
     * @return BelongsTo<User, $this>
     */
    public function audioReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'audio_reviewed_by');
    }

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function duplicatedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicated_from_id');
    }

    /**
     * @return HasMany<CampaignVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(CampaignVersion::class);
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
     * Every call this campaign has dialled, including redials.
     *
     * @return HasMany<VoiceCall, $this>
     */
    public function voiceCalls(): HasMany
    {
        return $this->hasMany(VoiceCall::class);
    }

    /**
     * Every message this campaign has submitted, including resends.
     *
     * @return HasMany<SmsMessage, $this>
     */
    public function smsMessages(): HasMany
    {
        return $this->hasMany(SmsMessage::class);
    }

    /**
     * Every email this campaign has submitted, including resends.
     *
     * @return HasMany<EmailMessage, $this>
     */
    public function emailMessages(): HasMany
    {
        return $this->hasMany(EmailMessage::class);
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

    /**
     * Limit the query to campaigns whose voice audio still needs a review.
     */
    #[Scope]
    protected function awaitingAudioReview(Builder $query): void
    {
        $query->whereIn($query->qualifyColumn('channel'), [
            Channel::Voice->value,
            Channel::MultiChannel->value,
        ])->where('audio_status', CampaignAudioStatus::Pending->value);
    }

    /**
     * Whether the campaign plays audio a reviewer has to approve.
     */
    public function requiresAudioApproval(): bool
    {
        return $this->channel->requiresVoiceContent();
    }

    /**
     * Whether the audio has already been cleared by a reviewer.
     */
    public function hasApprovedAudio(): bool
    {
        return $this->audio_status->isApproved();
    }
}
