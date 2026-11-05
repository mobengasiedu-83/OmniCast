<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One day of delivery totals for a client and channel.
 *
 * The row is a rollup, not an event: `reports:aggregate` recomputes it from the
 * campaign recipients so a rerun repairs a day instead of doubling it.
 *
 * @property int $id
 * @property int $client_id
 * @property Carbon $date
 * @property string $channel
 * @property int $campaigns
 * @property int $recipients
 * @property int $delivered
 * @property int $failed
 * @property int $skipped
 * @property string $spend
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'client_id', 'date', 'channel', 'campaigns', 'recipients', 'delivered',
    'failed', 'skipped', 'spend',
])]
class ReportDailyStat extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'campaigns' => 'integer',
            'recipients' => 'integer',
            'delivered' => 'integer',
            'failed' => 'integer',
            'skipped' => 'integer',
            'spend' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
