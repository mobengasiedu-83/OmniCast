<?php

use App\Enums\AnnouncementAudience;
use App\Enums\NotificationCategory;
use App\Models\Announcement;
use App\Models\AnnouncementRecipient;
use App\Models\User;
use App\Services\Notifications\AnnouncementDispatcher;
use App\Services\Notifications\NotificationDispatcher;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();

    [$this->client, $this->wallet, $this->user] = clientWithWallet();
});

/**
 * Put one notification in an account's inbox the way a system event would.
 *
 * @param  array<string, mixed>  $meta
 */
function pushNotification(
    User $user,
    NotificationCategory $category = NotificationCategory::Campaigns,
    string $title = 'Campaign completed',
    array $meta = [],
): void {
    app(NotificationDispatcher::class)->send(
        user: $user,
        category: $category,
        title: $title,
        body: 'The message body.',
        options: ['meta' => $meta],
    );
}

it('renders the caller inbox with only their own notifications', function (): void {
    pushNotification($this->user, NotificationCategory::Campaigns, 'Campaign completed');

    $this->travel(1)->minutes();

    pushNotification($this->user, NotificationCategory::Billing, 'Payment received');

    pushNotification(clientWithWallet()[2], NotificationCategory::Campaigns, 'Someone else');

    $this->actingAs($this->user)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('notifications/index')
            ->has('notifications.data', 2)
            ->where('notifications.data.0.title', 'Payment received')
            ->where('notifications.data.1.title', 'Campaign completed')
            ->where('counts.total', 2)
            ->where('counts.unread', 2)
            ->has('categories', count(NotificationCategory::cases()))
            ->where('filters.filter', 'all'));
});

it('filters the inbox by unread and by category', function (): void {
    pushNotification($this->user, NotificationCategory::Campaigns, 'Campaign completed');
    pushNotification($this->user, NotificationCategory::Billing, 'Payment received');

    $this->user->notifications()->where('data->category', 'billing')->firstOrFail()
        ->forceFill(['read_at' => now()])
        ->save();

    $this->actingAs($this->user)
        ->get(route('notifications.index', ['filter' => 'unread']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('notifications.data', 1)
            ->where('notifications.data.0.category', 'campaigns')
            ->where('counts.unread', 1));

    $this->actingAs($this->user)
        ->get(route('notifications.index', ['category' => 'billing']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('notifications.data', 1)
            ->where('notifications.data.0.title', 'Payment received')
            ->where('filters.category', 'billing'));
});

it('marks one notification read and puts it back to unread', function (): void {
    pushNotification($this->user);

    $notification = $this->user->notifications()->sole();

    $this->actingAs($this->user)
        ->post(route('notifications.read', ['notification' => $notification->id]))
        ->assertRedirect();

    expect($notification->refresh()->read_at)->not->toBeNull();

    $this->actingAs($this->user)
        ->post(route('notifications.unread', ['notification' => $notification->id]))
        ->assertRedirect();

    expect($notification->refresh()->read_at)->toBeNull();
});

it('will not read a notification that belongs to another account', function (): void {
    $other = clientWithWallet()[2];
    pushNotification($other);

    $notification = $other->notifications()->sole();

    $this->actingAs($this->user)
        ->post(route('notifications.read', ['notification' => $notification->id]))
        ->assertNotFound();

    $this->actingAs($this->user)
        ->delete(route('notifications.destroy', ['notification' => $notification->id]))
        ->assertNotFound();

    expect($notification->refresh()->read_at)->toBeNull();
});

it('marks every notification read at once', function (): void {
    pushNotification($this->user, NotificationCategory::Campaigns, 'One');
    pushNotification($this->user, NotificationCategory::Billing, 'Two');

    $this->actingAs($this->user)
        ->post(route('notifications.read-all'))
        ->assertRedirect()
        ->assertSessionHas('toast');

    expect($this->user->notifications()->whereNull('read_at')->count())->toBe(0);
});

it('deletes one notification and clears only the read ones', function (): void {
    pushNotification($this->user, NotificationCategory::Campaigns, 'Unread one');
    pushNotification($this->user, NotificationCategory::Billing, 'Read one');

    $this->user->notifications()->where('data->title', 'Read one')->firstOrFail()
        ->forceFill(['read_at' => now()])
        ->save();

    $this->actingAs($this->user)
        ->delete(route('notifications.clear-read'))
        ->assertRedirect();

    expect($this->user->notifications()->count())->toBe(1)
        ->and($this->user->notifications()->sole()->data['title'])->toBe('Unread one');

    $this->actingAs($this->user)
        ->delete(route('notifications.destroy', ['notification' => $this->user->notifications()->sole()->id]))
        ->assertRedirect();

    expect($this->user->notifications()->count())->toBe(0);
});

it('shares the unread count and the newest rows with the header bell', function (): void {
    pushNotification($this->user, NotificationCategory::Campaigns, 'Campaign completed');
    pushNotification($this->user, NotificationCategory::Billing, 'Payment received');

    $this->user->notifications()->where('data->title', 'Payment received')->firstOrFail()
        ->forceFill(['read_at' => now()])
        ->save();

    $this->actingAs($this->user)
        ->get(route('notifications.preferences.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('notificationCentre.unread_count', 1)
            ->has('notificationCentre.items', 1)
            ->where('notificationCentre.items.0.title', 'Campaign completed'));
});

it('records a read against the announcement that produced the notification', function (): void {
    $announcement = Announcement::factory()->for($this->client)->create([
        'audience' => AnnouncementAudience::Client,
        'client_id' => $this->client->id,
        'title' => 'Planned maintenance',
    ]);

    app(AnnouncementDispatcher::class)->deliverTo($announcement, $this->user);

    $recipient = AnnouncementRecipient::query()->sole();
    $notification = $this->user->notifications()->sole();

    expect($recipient->notification_id)->toBe($notification->id);

    $this->actingAs($this->user)
        ->post(route('notifications.read', ['notification' => $notification->id]))
        ->assertRedirect();

    expect($recipient->refresh()->read_at)->not->toBeNull()
        ->and($announcement->refresh()->read_count)->toBe(1);
});
