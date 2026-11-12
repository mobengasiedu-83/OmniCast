<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['campaign_id', 'contact_id', 'consent_id', 'phone', 'variables_json', 'eligibility', 'errors_json', 'snapshot_json', 'consent_checked_at', 'status', 'provider_message_id', 'attempt_count', 'next_attempt_at', 'submitted_at', 'accepted_at', 'unknown_at', 'delivered_at', 'failed_at', 'last_error_category', 'last_error', 'unit_price', 'unit_price_snapshot'])]
class WhatsAppCampaignRecipient extends Model
{
    protected $table = 'whatsapp_campaign_recipients';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['variables_json' => 'array', 'errors_json' => 'array', 'snapshot_json' => 'array', 'unit_price_snapshot' => 'array', 'unit_price' => 'decimal:4', 'consent_checked_at' => 'datetime', 'attempt_count' => 'integer', 'next_attempt_at' => 'datetime', 'submitted_at' => 'datetime', 'accepted_at' => 'datetime', 'unknown_at' => 'datetime', 'delivered_at' => 'datetime', 'read_at' => 'datetime', 'failed_at' => 'datetime'];
    }

    /** @return BelongsTo<WhatsAppCampaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(WhatsAppCampaign::class, 'campaign_id');
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** @return BelongsTo<WhatsAppContactConsent, $this> */
    public function consent(): BelongsTo
    {
        return $this->belongsTo(WhatsAppContactConsent::class, 'consent_id');
    }

    /** @return HasMany<WhatsAppMessageAttempt, $this> */
    public function attempts(): HasMany
    {
        return $this->hasMany(WhatsAppMessageAttempt::class, 'recipient_id')->orderBy('attempt_number');
    }

    /** @return HasMany<WhatsAppProviderEvent, $this> */
    public function providerEvents(): HasMany
    {
        return $this->hasMany(WhatsAppProviderEvent::class, 'recipient_id');
    }

    /** @return HasMany<WhatsAppUsageRecord, $this> */
    public function usageRecords(): HasMany
    {
        return $this->hasMany(WhatsAppUsageRecord::class, 'recipient_id');
    }

    protected static function booted(): void
    {
        static::updating(function (self $recipient): void {
            if ($recipient->isDirty(['campaign_id', 'contact_id', 'consent_id', 'phone', 'variables_json', 'eligibility', 'errors_json', 'snapshot_json', 'unit_price', 'unit_price_snapshot'])) {
                throw new \LogicException('WhatsApp campaign recipient snapshot data is immutable.');
            }
        });
    }
}
