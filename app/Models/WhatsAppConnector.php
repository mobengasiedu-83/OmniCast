<?php

namespace App\Models;

use App\Enums\WhatsAppConnectorStatus;
use Database\Factories\WhatsAppConnectorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A provider-owned WhatsApp Business Account connection.
 *
 * @property int $id
 * @property int $provider_id
 * @property string $external_id
 * @property string|null $waba_id
 * @property string|null $sender_number
 * @property WhatsAppConnectorStatus $status
 * @property Carbon|null $last_synced_at
 */
#[Fillable(['provider_id', 'external_id', 'waba_id', 'sender_number', 'status', 'last_synced_at'])]
class WhatsAppConnector extends Model
{
    protected $table = 'whatsapp_connectors';

    /** @use HasFactory<WhatsAppConnectorFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => WhatsAppConnectorStatus::class,
            'last_synced_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<WhatsAppProvider, $this> */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(WhatsAppProvider::class, 'provider_id');
    }

    /** @return HasMany<WhatsAppClientConnector, $this> */
    public function clientAssignments(): HasMany
    {
        return $this->hasMany(WhatsAppClientConnector::class, 'connector_id');
    }
}
