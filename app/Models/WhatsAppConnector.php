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
 * @property string|null $connector_name
 * @property string|null $waba_id
 * @property string|null $sender_number
 * @property WhatsAppConnectorStatus $status
 * @property Carbon|null $last_synced_at
 */
#[Fillable(['provider_id', 'client_id', 'external_id', 'connector_name', 'waba_id', 'business_id', 'access_token', 'token_expires_at', 'sender_number', 'status', 'last_synced_at'])]
class WhatsAppConnector extends Model
{
    protected $table = 'whatsapp_connectors';

    protected $hidden = ['access_token'];

    /** @use HasFactory<WhatsAppConnectorFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => WhatsAppConnectorStatus::class,
            'last_synced_at' => 'datetime',
            'access_token' => 'encrypted',
            'token_expires_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<WhatsAppProvider, $this> */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(WhatsAppProvider::class, 'provider_id');
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return HasMany<WhatsAppClientConnector, $this> */
    public function clientAssignments(): HasMany
    {
        return $this->hasMany(WhatsAppClientConnector::class, 'connector_id');
    }

    /** @return HasMany<WhatsAppTemplate, $this> */
    public function templates(): HasMany
    {
        return $this->hasMany(WhatsAppTemplate::class, 'connector_id');
    }
}
