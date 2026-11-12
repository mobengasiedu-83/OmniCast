<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['campaign_id', 'wallet_id', 'reference', 'amount', 'captured_amount', 'released_amount', 'status', 'unknown_deadline_at'])]
class WhatsAppWalletReservation extends Model
{
    protected $table = 'whatsapp_wallet_reservations';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['amount' => 'decimal:4', 'captured_amount' => 'decimal:4', 'released_amount' => 'decimal:4', 'unknown_deadline_at' => 'datetime', 'exception_flagged_at' => 'datetime'];
    }

    /** @return BelongsTo<WhatsAppCampaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(WhatsAppCampaign::class, 'campaign_id');
    }

    /** @return BelongsTo<Wallet, $this> */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function heldAmount(): float
    {
        return round((float) $this->amount - (float) $this->captured_amount - (float) $this->released_amount, 4);
    }
}
