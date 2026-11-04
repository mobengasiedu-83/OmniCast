<?php

namespace App\Models;

use App\Enums\ContactSuppressionReason;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $client_id
 * @property string $channel
 * @property string $identifier
 * @property ContactSuppressionReason $reason
 * @property string $source
 * @property bool $is_active
 * @property int|null $recorded_by
 * @property Carbon|null $suppressed_at
 * @property Carbon|null $lifted_at
 */
#[Fillable(['client_id', 'channel', 'identifier', 'reason', 'source', 'is_active', 'recorded_by', 'suppressed_at', 'lifted_at'])]
#[Appends(['reason_label'])]
class ContactSuppression extends Model
{
    /**
     * The channel value that blocks every delivery channel at once.
     */
    public const ALL_CHANNELS = 'all';

    protected function casts(): array
    {
        return [
            'reason' => ContactSuppressionReason::class,
            'is_active' => 'boolean',
            'suppressed_at' => 'datetime',
            'lifted_at' => 'datetime',
        ];
    }

    /**
     * The client that owns the suppression, or null for a platform-wide entry.
     *
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * The readable reason shown in the suppression registry.
     *
     * @return Attribute<string, never>
     */
    protected function reasonLabel(): Attribute
    {
        return Attribute::get(fn (): string => $this->reason->label());
    }
}
