<?php

namespace App\Models;

use App\Enums\CampaignStatus;
use App\Exceptions\CampaignSnapshotLocked;
use Database\Factories\CampaignVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $campaign_id
 * @property int $version
 * @property CampaignStatus $status
 * @property string $fingerprint
 * @property array<string, mixed> $snapshot
 * @property int $audience_size
 * @property string $estimated_cost
 * @property int|null $created_by
 * @property Carbon|null $frozen_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'campaign_id', 'version', 'status', 'fingerprint', 'snapshot',
    'audience_size', 'estimated_cost', 'created_by', 'frozen_at',
])]
class CampaignVersion extends Model
{
    /** @use HasFactory<CampaignVersionFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'status' => CampaignStatus::class,
            'snapshot' => 'array',
            'audience_size' => 'integer',
            'estimated_cost' => 'decimal:2',
            'frozen_at' => 'datetime',
        ];
    }

    /**
     * A recorded version is evidence: it may be created but never changed.
     */
    protected static function booted(): void
    {
        static::updating(function (CampaignVersion $version): void {
            throw CampaignSnapshotLocked::for($version->version);
        });

        static::deleting(function (CampaignVersion $version): void {
            throw CampaignSnapshotLocked::for($version->version);
        });
    }

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * The user who triggered the snapshot.
     *
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
