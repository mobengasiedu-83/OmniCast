<?php

namespace App\Models;

use App\Enums\CampaignStatus;
use App\Enums\Channel;
use Database\Factories\CampaignChannelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $campaign_id
 * @property Channel $channel
 * @property CampaignStatus $status
 * @property array<string, mixed>|null $config
 */
#[Fillable(['campaign_id', 'channel', 'status', 'config'])]
class CampaignChannel extends Model
{
    /** @use HasFactory<CampaignChannelFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => Channel::class,
            'status' => CampaignStatus::class,
            'config' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
