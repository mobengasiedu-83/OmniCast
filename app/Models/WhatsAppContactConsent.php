<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/** @property int $client_id @property int $contact_id @property string $purpose @property string $source @property string $wording @property Carbon $granted_at @property Carbon|null $revoked_at */
#[Fillable(['client_id', 'contact_id', 'purpose', 'source', 'wording', 'granted_at', 'revoked_at', 'recorded_by'])]
class WhatsAppContactConsent extends Model
{
    protected $table = 'whatsapp_contact_consents';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['granted_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** @return HasMany<WhatsAppCampaignRecipient, $this> */
    public function recipientSnapshots(): HasMany
    {
        return $this->hasMany(WhatsAppCampaignRecipient::class, 'consent_id');
    }

    protected static function booted(): void
    {
        static::updating(function (self $consent): void {
            if ($consent->isDirty(['client_id', 'contact_id', 'purpose', 'source', 'wording', 'granted_at'])) {
                throw new \LogicException('WhatsApp consent evidence is immutable; revoke it and record a new grant.');
            }
        });
    }
}
