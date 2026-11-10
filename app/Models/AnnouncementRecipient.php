<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One account's copy of an announcement.
 *
 * The row is what makes read-through measurable: the notification in the inbox
 * is the message, this is the receipt that says whether its owner opened it.
 *
 * @property int $id
 * @property int $announcement_id
 * @property int $user_id
 * @property string|null $notification_id
 * @property Carbon|null $emailed_at
 * @property Carbon|null $texted_at
 * @property Carbon|null $read_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['announcement_id', 'user_id', 'notification_id', 'emailed_at', 'texted_at', 'read_at'])]
class AnnouncementRecipient extends Model
{
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'emailed_at' => 'datetime',
            'texted_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Announcement, $this>
     */
    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Whether the account has opened the announcement.
     */
    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    /**
     * Stamp the moment the account opened the announcement.
     */
    public function markRead(): void
    {
        if ($this->read_at === null) {
            $this->forceFill(['read_at' => now()])->save();
        }
    }
}
