<?php

use App\Enums\AnnouncementStatus;
use App\Enums\NotificationCategory;
use App\Models\Announcement;
use App\Models\AnnouncementRecipient;
use App\Models\User;
use App\Services\Notifications\AnnouncementDispatcher;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    [$this->client, $this->wallet, $this->user] = clientWithWallet();
});

it('prunes read notifications past the window and leaves unread ones alone', function (): void {
    $dispatcher = app(NotificationDispatcher::class);

    $dispatcher->send($this->user, NotificationCategory::Campaigns, 'Read one', 'Body.');
    $dispatcher->send($this->user, NotificationCategory::Campaigns, 'Unread one', 'Body.');

    $this->user->notifications()->where('data->title', 'Read one')->firstOrFail()
        ->forceFill(['read_at' => now()->subDays(30)])
        ->save();

    $this->artisan('notifications:prune', ['--read-days' => 7])
        ->expectsOutputToContain('Pruned 1 read notification(s)')
        ->assertSuccessful();

    expect($this->user->notifications()->count())->toBe(1)
        ->and($this->user->notifications()->sole()->data['title'])->toBe('Unread one');
});

it('reports a dry run without deleting anything', function (): void {
    app(NotificationDispatcher::class)->send($this->user, NotificationCategory::Campaigns, 'Read one', 'Body.');

    $this->user->notifications()->sole()
        ->forceFill(['read_at' => now()->subDays(30)])
        ->save();

    $this->artisan('notifications:prune', ['--read-days' => 7, '--dry-run' => true])
        ->expectsOutputToContain('Would prune 1 read notification(s)')
        ->assertSuccessful();

    expect($this->user->notifications()->count())->toBe(1);
});

it('deletes a delivered announcement past its window with its receipts', function (): void {
    $old = Announcement::factory()->forClient($this->client)->create();
    app(AnnouncementDispatcher::class)->deliver($old);

    $old->forceFill([
        'status' => AnnouncementStatus::Sent,
        'sent_at' => now()->subYears(2),
    ])->save();

    $recent = Announcement::factory()->forClient($this->client)->create();
    app(AnnouncementDispatcher::class)->deliver($recent);

    $this->artisan('notifications:prune', ['--sent-days' => 365])
        ->expectsOutputToContain('and 1 sent announcement(s).')
        ->assertSuccessful();

    expect(Announcement::query()->pluck('id')->all())->toBe([$recent->id])
        ->and(AnnouncementRecipient::query()->where('announcement_id', $old->id)->count())->toBe(0)
        ->and(AnnouncementRecipient::query()->where('announcement_id', $recent->id)->count())->toBe(1)
        ->and($this->user->notifications()->count())->toBe(2);
});

it('sweeps notifications whose account no longer exists', function (): void {
    $gone = User::factory()->forClient($this->client)->create();
    app(NotificationDispatcher::class)->send($gone, NotificationCategory::Campaigns, 'Orphan', 'Body.');

    DB::table('users')->where('id', $gone->id)->delete();

    expect(DB::table('notifications')->where('notifiable_id', $gone->id)->count())->toBe(1);

    $this->artisan('notifications:prune')
        ->expectsOutputToContain('Pruned 1 orphaned notification(s)')
        ->assertSuccessful();

    expect(DB::table('notifications')->where('notifiable_id', $gone->id)->count())->toBe(0);
});
