<?php

namespace App\Services\Notifications;

use App\Enums\AnnouncementAudience;
use App\Enums\AnnouncementStatus;
use App\Enums\ClientStatus;
use App\Jobs\SendAnnouncement;
use App\Models\Announcement;
use App\Models\AnnouncementRecipient;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Fans an announcement out to the accounts its audience resolves to.
 *
 * The audience is resolved when the fan-out runs rather than when it was
 * composed, so a draft sent tomorrow reaches the accounts that exist tomorrow.
 * Recipients are walked in chunks and each one is recorded, which is what makes
 * the read-through on the admin screen meaningful.
 */
class AnnouncementDispatcher
{
    public function __construct(protected NotificationDispatcher $notifications) {}

    /**
     * Hand a composed announcement to the queue.
     */
    public function queue(Announcement $announcement): Announcement
    {
        if ($announcement->isSent()) {
            return $announcement;
        }

        $announcement->forceFill(['status' => AnnouncementStatus::Queued])->save();

        SendAnnouncement::dispatch($announcement->id);

        return $announcement;
    }

    /**
     * Deliver the announcement to every account in its audience, once.
     */
    public function deliver(Announcement $announcement): Announcement
    {
        $announcement->refresh();

        if ($announcement->isSent()) {
            return $announcement;
        }

        $announcement->forceFill(['status' => AnnouncementStatus::Queued])->save();

        $delivered = 0;
        $chunk = max((int) config('notifications.announcements.chunk', 200), 1);

        $this->audience($announcement)->chunkById($chunk, function (Collection $users) use ($announcement, &$delivered): void {
            foreach ($users as $user) {
                if ($this->deliverTo($announcement, $user) !== null) {
                    $delivered++;
                }
            }
        });

        $announcement->markSent($delivered);

        return $announcement->refresh();
    }

    /**
     * Deliver the announcement to one account, skipping a repeat.
     */
    public function deliverTo(Announcement $announcement, User $user): ?AnnouncementRecipient
    {
        $recipient = AnnouncementRecipient::query()->firstOrNew([
            'announcement_id' => $announcement->id,
            'user_id' => $user->id,
        ]);

        if ($recipient->exists) {
            return $recipient;
        }

        $delivery = $this->notifications->send(
            user: $user,
            category: $announcement->category,
            title: $announcement->title,
            body: $announcement->body,
            options: [
                'action_url' => route('notifications.index'),
                'action_label' => 'Open notifications',
                'meta' => ['announcement_id' => $announcement->id],
                'severity' => $announcement->severity,
                'force_in_app' => true,
                'allow_email' => $announcement->send_email,
            ],
        );

        if ($delivery === null) {
            return null;
        }

        $recipient->forceFill([
            'notification_id' => $delivery->id(),
            'emailed_at' => $delivery->emailed ? now() : null,
        ])->save();

        return $recipient;
    }

    /**
     * The accounts the audience resolves to, right now.
     *
     * @return Builder<User>
     */
    public function audience(Announcement $announcement): Builder
    {
        $query = User::query()
            ->where('users.is_active', true)
            ->whereNull('users.suspended_at');

        return match ($announcement->audience) {
            AnnouncementAudience::Everyone => $query,
            AnnouncementAudience::Platform => $query->whereNull('users.client_id'),
            AnnouncementAudience::Clients => $query->whereNotNull('users.client_id')
                ->whereHas('client', fn (Builder $client): Builder => $client->where('status', ClientStatus::Active->value)),
            AnnouncementAudience::Client => $query->where('users.client_id', $announcement->client_id),
            AnnouncementAudience::Role => $query->role((string) $announcement->role),
        };
    }
}
