<?php

namespace App\Models;

use App\Enums\NotificationCategory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The channels one account wants one category on.
 *
 * A missing row is not "off": it means the defaults in
 * `config/notifications.php` apply, so adding a category does not quietly mute
 * it for accounts created before it existed.
 *
 * @property int $id
 * @property int $user_id
 * @property NotificationCategory $category
 * @property bool $in_app
 * @property bool $email
 * @property bool $sms
 */
#[Fillable(['user_id', 'category', 'in_app', 'email', 'sms'])]
class NotificationPreference extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => NotificationCategory::class,
            'in_app' => 'boolean',
            'email' => 'boolean',
            'sms' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Whether the account wants the given channel for its category.
     */
    public function allows(string $channel): bool
    {
        return match ($channel) {
            'in_app' => $this->in_app,
            'email' => $this->email,
            'sms' => $this->sms,
            default => false,
        };
    }
}
