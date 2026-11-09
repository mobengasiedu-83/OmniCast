<?php

namespace App\Http\Controllers;

use App\Enums\NotificationCategory;
use App\Models\AnnouncementRecipient;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The signed-in account's own inbox.
 *
 * Every row belongs to exactly one notifiable, and each action reads the row
 * through the caller's own relation, so another account's notification is a 404
 * rather than a permission check that could be bypassed.
 */
class NotificationController extends Controller
{
    /**
     * List the caller's notifications with the filters the screen offers.
     */
    public function index(Request $request): Response
    {
        $user = $this->account($request);
        $filter = $request->string('filter')->toString() === 'unread' ? 'unread' : 'all';
        $category = NotificationCategory::tryFrom($request->string('category')->toString());

        $notifications = $user->notifications()
            ->when($filter === 'unread', fn ($query) => $query->whereNull('read_at'))
            ->when($category !== null, fn ($query) => $query->where('data->category', $category->value))
            ->orderByDesc('created_at')
            ->paginate(max((int) config('notifications.inbox.per_page', 20), 1))
            ->withQueryString()
            ->through(fn (DatabaseNotification $notification): array => $this->payload($notification));

        return Inertia::render('notifications/index', [
            'notifications' => $notifications,
            'filters' => [
                'filter' => $filter,
                'category' => $category?->value,
            ],
            'counts' => [
                'total' => $user->notifications()->count(),
                'unread' => $user->notifications()->whereNull('read_at')->count(),
            ],
            'categories' => NotificationCategory::catalogue(),
        ]);
    }

    /**
     * Mark one notification as read.
     */
    public function markRead(Request $request, string $notification): RedirectResponse
    {
        $row = $this->findNotification($request, $notification);

        if ($row->read_at === null) {
            $row->forceFill(['read_at' => now()])->save();
        }

        $this->markAnnouncementRead($request, $row);

        return back();
    }

    /**
     * Put one notification back to unread.
     */
    public function markUnread(Request $request, string $notification): RedirectResponse
    {
        $this->findNotification($request, $notification)->forceFill(['read_at' => null])->save();

        return back();
    }

    /**
     * Empty the unread badge.
     */
    public function markAllRead(Request $request): RedirectResponse
    {
        $this->account($request)->notifications()->whereNull('read_at')->update(['read_at' => now()]);

        $this->markAnnouncementsRead($request);

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Every notification is marked as read.',
        ]);
    }

    /**
     * Delete one notification.
     */
    public function destroy(Request $request, string $notification): RedirectResponse
    {
        $this->findNotification($request, $notification)->delete();

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'The notification was removed.',
        ]);
    }

    /**
     * Delete every notification the account has already read.
     */
    public function clearRead(Request $request): RedirectResponse
    {
        $deleted = $this->account($request)->notifications()->whereNotNull('read_at')->delete();

        return back()->with('toast', [
            'type' => 'success',
            'message' => $deleted === 0
                ? 'There was nothing read to clear.'
                : "Removed {$deleted} read notification(s).",
        ]);
    }

    /**
     * The signed-in account, or a 401 when the route somehow ran unauthenticated.
     */
    protected function account(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        return $user;
    }

    /**
     * The caller's own row, or a 404 when it belongs to somebody else.
     */
    protected function findNotification(Request $request, string $id): DatabaseNotification
    {
        /** @var DatabaseNotification $notification */
        $notification = $this->account($request)->notifications()->whereKey($id)->firstOrFail();

        return $notification;
    }

    /**
     * Record that an announcement's recipient opened its notification.
     */
    protected function markAnnouncementRead(Request $request, DatabaseNotification $notification): void
    {
        $announcementId = (int) (data_get($notification->data, 'meta.announcement_id') ?? 0);

        if ($announcementId === 0) {
            return;
        }

        $recipient = AnnouncementRecipient::query()
            ->where('announcement_id', $announcementId)
            ->where('user_id', $this->account($request)->id)
            ->first();

        if ($recipient === null) {
            return;
        }

        $recipient->markRead();
        $recipient->announcement?->refreshReadCount();
    }

    /**
     * Record every pending announcement receipt as read.
     */
    protected function markAnnouncementsRead(Request $request): void
    {
        $user = $this->account($request);

        $pending = AnnouncementRecipient::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->get();

        foreach ($pending as $recipient) {
            $stillUnread = $recipient->notification_id !== null
                && $user->notifications()
                    ->whereKey($recipient->notification_id)
                    ->whereNull('read_at')
                    ->exists();

            if ($stillUnread) {
                continue;
            }

            $recipient->markRead();
            $recipient->announcement?->refreshReadCount();
        }
    }

    /**
     * The screen shape of one inbox row.
     *
     * @return array<string, mixed>
     */
    protected function payload(DatabaseNotification $notification): array
    {
        $category = NotificationCategory::tryFrom((string) data_get($notification->data, 'category', ''))
            ?? NotificationCategory::System;

        return [
            'id' => $notification->id,
            'category' => $category->value,
            'category_label' => $category->label(),
            'tone' => $category->tone(),
            'severity' => (string) data_get($notification->data, 'severity', 'info'),
            'title' => (string) data_get($notification->data, 'title', 'Notification'),
            'body' => (string) data_get($notification->data, 'body', ''),
            'action_url' => data_get($notification->data, 'action_url'),
            'action_label' => data_get($notification->data, 'action_label'),
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
        ];
    }
}
