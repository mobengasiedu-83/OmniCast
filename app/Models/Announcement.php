<?php

namespace App\Models;

use App\Enums\AnnouncementAudience;
use App\Enums\AnnouncementSeverity;
use App\Enums\AnnouncementStatus;
use App\Enums\NotificationCategory;
use App\Enums\Role;
use Database\Factories\AnnouncementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A message the platform team composes once and delivers to an audience.
 *
 * The row is the definition; `announcement_recipients` records who actually
 * received it, so the admin screen can show how much of the audience read it
 * rather than only how many were addressed.
 *
 * @property int $id
 * @property string $title
 * @property string $body
 * @property NotificationCategory $category
 * @property AnnouncementAudience $audience
 * @property int|null $client_id
 * @property string|null $role
 * @property AnnouncementSeverity $severity
 * @property bool $send_email
 * @property AnnouncementStatus $status
 * @property Carbon|null $sent_at
 * @property int $recipient_count
 * @property int $read_count
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'title', 'body', 'category', 'audience', 'client_id', 'role', 'severity',
    'send_email', 'status', 'sent_at', 'recipient_count', 'read_count', 'created_by',
])]
class Announcement extends Model
{
    /** @use HasFactory<AnnouncementFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => NotificationCategory::class,
            'audience' => AnnouncementAudience::class,
            'severity' => AnnouncementSeverity::class,
            'status' => AnnouncementStatus::class,
            'send_email' => 'boolean',
            'sent_at' => 'datetime',
            'recipient_count' => 'integer',
            'read_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<AnnouncementRecipient, $this>
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(AnnouncementRecipient::class);
    }

    /**
     * Limit the query to announcements that have not gone out yet.
     */
    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->whereIn('status', [AnnouncementStatus::Draft->value, AnnouncementStatus::Queued->value]);
    }

    /**
     * Order the newest announcement first.
     */
    #[Scope]
    protected function latestFirst(Builder $query): void
    {
        $query->latest('id');
    }

    /**
     * Whether the announcement has already been delivered.
     */
    public function isSent(): bool
    {
        return $this->status->isSent();
    }

    /**
     * Whether the announcement is still waiting for its fan-out.
     */
    public function isPending(): bool
    {
        return ! $this->isSent();
    }

    /**
     * Who the audience names, for the screens.
     */
    public function audienceLabel(): string
    {
        return match ($this->audience) {
            AnnouncementAudience::Client => $this->client?->name ?? 'One client account',
            AnnouncementAudience::Role => Role::tryFrom((string) $this->role)?->label() ?? 'One role',
            default => $this->audience->label(),
        };
    }

    /**
     * Record the result of a fan-out.
     */
    public function markSent(int $recipientCount): void
    {
        $this->forceFill([
            'status' => AnnouncementStatus::Sent,
            'sent_at' => now(),
            'recipient_count' => $recipientCount,
        ])->save();
    }

    /**
     * Re-count how many recipients have opened the announcement.
     */
    public function refreshReadCount(): int
    {
        $read = $this->recipients()->whereNotNull('read_at')->count();

        $this->forceFill(['read_count' => $read])->save();

        return $read;
    }
}
