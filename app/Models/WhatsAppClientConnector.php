<?php

namespace App\Models;

use App\Enums\WhatsAppApprovalStatus;
use Database\Factories\WhatsAppClientConnectorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A connector assignment and approval record for one client tenant.
 *
 * @property int $id
 * @property int $client_id
 * @property int $connector_id
 * @property bool $enabled
 * @property WhatsAppApprovalStatus $approval_status
 * @property int|null $approved_by
 * @property int|null $created_by
 * @property Carbon|null $approved_at
 */
#[Fillable(['client_id', 'connector_id', 'enabled', 'approval_status', 'approved_by', 'created_by', 'approved_at'])]
class WhatsAppClientConnector extends Model
{
    protected $table = 'whatsapp_client_connectors';

    /** @use HasFactory<WhatsAppClientConnectorFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'approval_status' => WhatsAppApprovalStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<WhatsAppConnector, $this> */
    public function connector(): BelongsTo
    {
        return $this->belongsTo(WhatsAppConnector::class, 'connector_id');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
