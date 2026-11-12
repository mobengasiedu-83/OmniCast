<?php

namespace App\Models;

use App\Enums\WhatsAppProviderStatus;
use Database\Factories\WhatsAppProviderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A configured WhatsApp Business messaging provider.
 *
 * @property int $id
 * @property string $name
 * @property string $driver
 * @property WhatsAppProviderStatus $status
 * @property bool $is_enabled
 * @property int $connect_timeout
 * @property int $request_timeout
 * @property Carbon|null $tested_at
 * @property string|null $last_test_error
 * @property int|null $created_by
 * @property int|null $updated_by
 */
#[Fillable(['name', 'driver', 'status', 'is_enabled', 'connect_timeout', 'request_timeout', 'tested_at', 'last_test_error', 'created_by', 'updated_by'])]
class WhatsAppProvider extends Model
{
    protected $table = 'whatsapp_providers';

    /** @use HasFactory<WhatsAppProviderFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => WhatsAppProviderStatus::class,
            'is_enabled' => 'boolean',
            'connect_timeout' => 'integer',
            'request_timeout' => 'integer',
            'tested_at' => 'datetime',
        ];
    }

    /** @return HasMany<WhatsAppConnector, $this> */
    public function connectors(): HasMany
    {
        return $this->hasMany(WhatsAppConnector::class, 'provider_id');
    }

    /** @return HasMany<WhatsAppAuditLog, $this> */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(WhatsAppAuditLog::class, 'subject_id')
            ->where('subject_type', self::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
