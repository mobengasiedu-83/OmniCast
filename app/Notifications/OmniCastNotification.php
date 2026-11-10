<?php

namespace App\Notifications;

use App\Enums\AnnouncementSeverity;
use App\Enums\NotificationCategory;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The email half of a notification centre message.
 *
 * `NotificationDispatcher` writes the in-app row itself, because it has to hand
 * the row id back to an announcement. That leaves this class responsible for
 * the email copy only, and it is only ever sent when the account has switched
 * the category's email channel on.
 */
class OmniCastNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public NotificationCategory $category,
        public string $title,
        public string $body,
        public ?string $actionUrl = null,
        public ?string $actionLabel = null,
        public array $meta = [],
        public AnnouncementSeverity $severity = AnnouncementSeverity::Info,
    ) {}

    /**
     * The channels this notification travels on.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * The shape the inbox row is stored in.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'category' => $this->category->value,
            'title' => $this->title,
            'body' => $this->body,
            'severity' => $this->severity->value,
            'action_url' => $this->actionUrl,
            'action_label' => $this->actionLabel,
            'meta' => $this->meta,
        ];
    }

    /**
     * The array form, used when the notification is queued through a channel.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->payload();
    }

    /**
     * The email copy.
     *
     * The copy is a branded view rather than a stack of `line()` calls, so an
     * announcement looks like the product it came from and the receipt mails the
     * billing category sends share the same chrome.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title)
            ->view('mail.notification', [
                'title' => $this->title,
                'body' => $this->body,
                'actionUrl' => $this->actionUrl,
                'actionLabel' => $this->actionLabel,
                'categoryLabel' => $this->category->label(),
                'severity' => $this->severity,
                'preferencesUrl' => route('notifications.preferences.edit'),
            ]);
    }
}
