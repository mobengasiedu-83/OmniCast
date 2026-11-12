<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['client_id', 'connector_id', 'template_id', 'name', 'status', 'contact_list_id', 'contact_segment_id', 'variable_mapping', 'media_asset_id', 'schedule_type', 'scheduled_at', 'timezone', 'estimated_recipients', 'estimated_cost', 'estimate_currency', 'estimate_disclaimer', 'price_snapshot', 'wallet_reservation_id', 'created_by', 'control_state', 'dispatch_uuid', 'dispatch_started_at', 'completed_at'])]
class WhatsAppCampaign extends Model
{
    protected $table = 'whatsapp_campaigns';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['variable_mapping' => 'array', 'price_snapshot' => 'array', 'scheduled_at' => 'datetime', 'dispatch_started_at' => 'datetime', 'completed_at' => 'datetime', 'estimated_recipients' => 'integer', 'estimated_cost' => 'decimal:4', 'estimate_disclaimer' => 'array'];
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<WhatsAppConnector, $this> */
    public function connector(): BelongsTo
    {
        return $this->belongsTo(WhatsAppConnector::class);
    }

    /** @return BelongsTo<WhatsAppTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(WhatsAppTemplate::class);
    }

    /** @return BelongsTo<WhatsAppMediaAsset, $this> */
    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(WhatsAppMediaAsset::class, 'media_asset_id');
    }

    /** @return BelongsTo<WhatsAppWalletReservation, $this> */
    public function walletReservation(): BelongsTo
    {
        return $this->belongsTo(WhatsAppWalletReservation::class, 'wallet_reservation_id');
    }

    /** @return HasMany<WhatsAppCampaignRecipient, $this> */
    public function recipients(): HasMany
    {
        return $this->hasMany(WhatsAppCampaignRecipient::class, 'campaign_id');
    }

    protected static function booted(): void
    {
        static::updating(function (self $campaign): void {
            $sourceChanged = $campaign->isDirty([
                'client_id', 'connector_id', 'template_id', 'contact_list_id', 'contact_segment_id', 'variable_mapping', 'media_asset_id',
            ]);
            $savedPriceChanged = $campaign->getOriginal('price_snapshot') !== null && $campaign->isDirty('price_snapshot');
            if ($campaign->recipients()->exists() && ($sourceChanged || $savedPriceChanged)) {
                throw new \LogicException('A WhatsApp campaign with recipient snapshots cannot change its source inputs. Create a new draft or clone it.');
            }
        });
    }
}
