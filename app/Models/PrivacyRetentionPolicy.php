<?php

namespace App\Models;

use App\Enums\PrivacyRetentionCategory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A retention period and the recorded legal approval that enables pruning.
 *
 * @property int $id
 * @property PrivacyRetentionCategory $category
 * @property int|null $retention_days
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 */
#[Fillable(['category', 'retention_days', 'approved_by', 'approved_at'])]
class PrivacyRetentionPolicy extends Model
{
    protected function casts(): array
    {
        return [
            'category' => PrivacyRetentionCategory::class,
            'retention_days' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * The administrator who approved this retention period.
     *
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
