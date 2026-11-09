<?php

namespace App\Services\Notifications;

use App\Enums\AnnouncementSeverity;
use App\Enums\NotificationCategory;
use App\Models\User;
use App\Notifications\OmniCastNotification;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;

/**
 * Writes a notification into an account's inbox and, when the account asked for
 * it, mails the same message.
 *
 * The inbox row is written here rather than by Laravel's database channel so the
 * caller gets the row back: an announcement records the notification it produced
 * and the read receipt hangs off that id. The email copy travels as a
 * {@see OmniCastNotification}, whose only channel is mail.
 */
class NotificationDispatcher
{
    public function __construct(protected NotificationPreferences $preferences) {}

    /**
     * Deliver one message to one account.
     *
     * `force_in_app` is used by announcements: a message addressed to the account
     * always lands in the inbox, whatever the account chose for the category.
     * `allow_email` lets an announcement's own switch veto the email copy.
     *
     * @param  array{
     *     action_url?: string|null,
     *     action_label?: string|null,
     *     meta?: array<string, mixed>,
     *     severity?: AnnouncementSeverity,
     *     force_in_app?: bool,
     *     allow_email?: bool
     * }  $options
     */
    public function send(
        User $user,
        NotificationCategory $category,
        string $title,
        string $body,
        array $options = [],
    ): ?NotificationDelivery {
        if (! (bool) config('notifications.enabled', true)) {
            return null;
        }

        $inApp = ($options['force_in_app'] ?? false)
            || $this->preferences->allows($user, $category, 'in_app');
        $email = ($options['allow_email'] ?? true)
            && $this->preferences->allows($user, $category, 'email');

        if (! $inApp && ! $email) {
            return null;
        }

        $notification = new OmniCastNotification(
            category: $category,
            title: $title,
            body: $body,
            actionUrl: $options['action_url'] ?? null,
            actionLabel: $options['action_label'] ?? null,
            meta: $options['meta'] ?? [],
            severity: $options['severity'] ?? AnnouncementSeverity::Info,
        );

        if ($email) {
            $user->notify($notification);
        }

        return new NotificationDelivery(
            notification: $inApp ? $this->write($user, $notification) : null,
            emailed: $email,
        );
    }

    /**
     * Deliver the same message to several accounts.
     *
     * @param  iterable<User>  $users
     * @param  array<string, mixed>  $options
     */
    public function sendMany(
        iterable $users,
        NotificationCategory $category,
        string $title,
        string $body,
        array $options = [],
    ): int {
        $sent = 0;

        foreach ($users as $user) {
            if ($this->send($user, $category, $title, $body, $options) !== null) {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Write the inbox row the account reads on `/notifications`.
     */
    protected function write(User $user, OmniCastNotification $notification): DatabaseNotification
    {
        /** @var DatabaseNotification $row */
        $row = $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => OmniCastNotification::class,
            'data' => $notification->payload(),
        ]);

        return $row;
    }
}
