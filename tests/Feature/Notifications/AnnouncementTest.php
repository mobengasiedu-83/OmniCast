<?php

use App\Enums\AnnouncementAudience;
use App\Enums\AnnouncementSeverity;
use App\Enums\AnnouncementStatus;
use App\Enums\NotificationCategory;
use App\Enums\Role;
use App\Jobs\SendAnnouncement;
use App\Models\Announcement;
use App\Models\AnnouncementRecipient;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Notifications\OmniCastNotification;
use App\Services\Notifications\AnnouncementDispatcher;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();

    $this->admin = platformUser();
});

/**
 * The composer's payload, with whatever the test wants to change.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function announcementPayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'Planned maintenance',
        'body' => 'The platform is read-only on Saturday between 02:00 and 04:00.',
        'category' => NotificationCategory::Platform->value,
        'audience' => AnnouncementAudience::Everyone->value,
        'severity' => AnnouncementSeverity::Info->value,
        'send_email' => false,
        'send_now' => true,
    ], $overrides);
}

it('renders the messaging centre for the platform team', function (): void {
    Announcement::factory()->create(['title' => 'Maintenance window']);
    Announcement::factory()->sent(4)->create(['title' => 'Go live']);

    $this->actingAs($this->admin)
        ->get(route('admin.announcements.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/announcements/index')
            ->has('announcements.data', 2)
            ->where('announcements.data.0.title', 'Go live')
            ->has('audiences', count(AnnouncementAudience::cases()))
            ->has('categories', count(NotificationCategory::cases()))
            ->has('severities', count(AnnouncementSeverity::cases()))
            ->has('roles', count(Role::cases()))
            ->where('stats.drafts', 1)
            ->where('stats.sent', 1)
            ->where('stats.recipients', 4));
});

it('refuses the messaging centre to a client user', function (): void {
    $clientUser = clientWithWallet()[2];

    $this->actingAs($clientUser)
        ->get(route('admin.announcements.index'))
        ->assertForbidden();

    $this->actingAs($clientUser)
        ->post(route('admin.announcements.store'), announcementPayload())
        ->assertForbidden();
});

it('saves a draft without delivering anything', function (): void {
    Queue::fake();

    $this->actingAs($this->admin)
        ->post(route('admin.announcements.store'), announcementPayload([
            'send_now' => false,
            'send_email' => true,
        ]))
        ->assertRedirect()
        ->assertSessionHas('toast');

    $announcement = Announcement::query()->sole();

    expect($announcement->status)->toBe(AnnouncementStatus::Draft)
        ->and($announcement->send_email)->toBeTrue()
        ->and($announcement->recipient_count)->toBe(0)
        ->and($announcement->created_by)->toBe($this->admin->id);

    Queue::assertNothingPushed();
});

it('holds an announcement until the send time the composer names', function (): void {
    Queue::fake();

    $sendAt = now()->addDay()->startOfMinute();

    $this->actingAs($this->admin)
        ->post(route('admin.announcements.store'), announcementPayload([
            'send_now' => false,
            'scheduled_at' => $sendAt->toIso8601String(),
        ]))
        ->assertRedirect()
        ->assertSessionHas('toast');

    $announcement = Announcement::query()->sole();

    expect($announcement->status)->toBe(AnnouncementStatus::Scheduled)
        ->and($announcement->scheduled_at?->equalTo($sendAt))->toBeTrue()
        ->and($announcement->sent_at)->toBeNull()
        ->and($announcement->recipient_count)->toBe(0);

    Queue::assertNothingPushed();
});

it('refuses a send time that has already passed', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.announcements.store'), announcementPayload([
            'send_now' => false,
            'scheduled_at' => now()->subHour()->toIso8601String(),
        ]))
        ->assertSessionHasErrors('scheduled_at');

    expect(Announcement::query()->count())->toBe(0);
});

it('refuses a send time when the composer also asked to deliver now', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.announcements.store'), announcementPayload([
            'send_now' => true,
            'scheduled_at' => now()->addDay()->toIso8601String(),
        ]))
        ->assertSessionHasErrors('scheduled_at');

    expect(Announcement::query()->count())->toBe(0);
});

it('lists a scheduled announcement under its own filter', function (): void {
    Announcement::factory()->create(['title' => 'Draft copy']);
    Announcement::factory()->create([
        'title' => 'Saturday maintenance',
        'status' => AnnouncementStatus::Scheduled,
        'scheduled_at' => now()->addDay(),
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.announcements.index', ['status' => AnnouncementStatus::Scheduled->value]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('announcements.data', 1)
            ->where('announcements.data.0.title', 'Saturday maintenance')
            ->where('announcements.data.0.status', 'scheduled')
            ->where('announcements.data.0.scheduled_at', fn (?string $value): bool => $value !== null)
            ->where('filters.status', 'scheduled')
            ->where('stats.drafts', 1)
            ->where('stats.scheduled', 1));
});

it('queues a scheduled announcement once its send time arrives', function (): void {
    Queue::fake();

    $due = Announcement::factory()->create([
        'status' => AnnouncementStatus::Scheduled,
        'scheduled_at' => now()->subMinutes(5),
    ]);

    $later = Announcement::factory()->create([
        'status' => AnnouncementStatus::Scheduled,
        'scheduled_at' => now()->addHours(2),
    ]);

    $this->artisan('announcements:send-scheduled')
        ->expectsOutputToContain('Queued 1 scheduled announcement(s).')
        ->assertSuccessful();

    expect($due->refresh()->status)->toBe(AnnouncementStatus::Queued)
        ->and($later->refresh()->status)->toBe(AnnouncementStatus::Scheduled);

    Queue::assertPushed(SendAnnouncement::class, 1);
});

it('puts a scheduled announcement back to a draft when its send time is cleared', function (): void {
    $announcement = Announcement::factory()->create([
        'status' => AnnouncementStatus::Scheduled,
        'scheduled_at' => now()->addDay(),
    ]);

    $this->actingAs($this->admin)
        ->post(route('admin.announcements.cancel-schedule', ['announcement' => $announcement->id]))
        ->assertRedirect()
        ->assertSessionHas('toast');

    expect($announcement->refresh()->status)->toBe(AnnouncementStatus::Draft)
        ->and($announcement->scheduled_at)->toBeNull();

    $this->actingAs($this->admin)
        ->post(route('admin.announcements.cancel-schedule', ['announcement' => $announcement->id]))
        ->assertRedirect()
        ->assertSessionHas('toast', fn (array $toast): bool => $toast['type'] === 'error');
});

it('lists who received a sent announcement and whether they opened it', function (): void {
    [$client, , $user] = clientWithWallet();
    $announcement = Announcement::factory()->forClient($client)->create();

    app(AnnouncementDispatcher::class)->deliverTo($announcement, $user);

    $recipient = AnnouncementRecipient::query()->sole();
    $recipient->forceFill([
        'read_at' => now(),
        'emailed_at' => now(),
        'texted_at' => now(),
    ])->save();
    $announcement->forceFill([
        'status' => AnnouncementStatus::Sent,
        'recipient_count' => 1,
        'read_count' => 1,
    ])->save();

    $this->actingAs($this->admin)
        ->get(route('admin.announcements.recipients', ['announcement' => $announcement->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/announcements/recipients')
            ->where('announcement.title', $announcement->title)
            ->where('announcement.recipient_count', 1)
            ->where('announcement.read_count', 1)
            ->has('recipients.data', 1)
            ->where('recipients.data.0.name', $user->name)
            ->where('recipients.data.0.email', $user->email)
            ->where('recipients.data.0.read_at', fn (?string $value): bool => $value !== null)
            ->where('recipients.data.0.emailed_at', fn (?string $value): bool => $value !== null)
            ->where('recipients.data.0.texted_at', fn (?string $value): bool => $value !== null)
            ->missing('recipients.data.0.phone'));
});

it('refuses the recipient report to a client account', function (): void {
    $announcement = Announcement::factory()->create();
    $clientUser = clientWithWallet()[2];

    $this->actingAs($clientUser)
        ->get(route('admin.announcements.recipients', ['announcement' => $announcement->id]))
        ->assertForbidden();
});

it('texts the copy when the platform, the composer and the account all allow it', function (): void {
    config()->set('notifications.sms.enabled', true);

    [$client, , $user] = clientWithWallet();
    $user->forceFill(['phone' => '+233201234567'])->save();

    NotificationPreference::query()->create([
        'user_id' => $user->id,
        'category' => NotificationCategory::Platform->value,
        'in_app' => true,
        'email' => false,
        'sms' => true,
    ]);

    $this->actingAs($this->admin)
        ->post(route('admin.announcements.store'), announcementPayload([
            'audience' => AnnouncementAudience::Client->value,
            'client_id' => $client->id,
            'send_sms' => true,
        ]))
        ->assertRedirect();

    $recipient = AnnouncementRecipient::query()->where('user_id', $user->id)->sole();

    expect($recipient->texted_at)->not->toBeNull()
        ->and($recipient->emailed_at)->toBeNull();
});

it('does not send an announcement SMS just because email was requested', function (): void {
    config()->set('notifications.sms.enabled', true);

    [$client, , $user] = clientWithWallet();
    $user->forceFill(['phone' => '+233201234567'])->save();
    NotificationPreference::query()->create([
        'user_id' => $user->id,
        'category' => NotificationCategory::Platform->value,
        'in_app' => true,
        'email' => false,
        'sms' => true,
    ]);

    $this->actingAs($this->admin)
        ->post(route('admin.announcements.store'), announcementPayload([
            'audience' => AnnouncementAudience::Client->value,
            'client_id' => $client->id,
            'send_email' => true,
            'send_sms' => false,
        ]))
        ->assertRedirect();

    $recipient = AnnouncementRecipient::query()->where('user_id', $user->id)->sole();

    expect($recipient->emailed_at)->toBeNull()
        ->and($recipient->texted_at)->toBeNull();
});

it('delivers to every client account and leaves the platform team alone', function (): void {
    [$firstClient, , $firstUser] = clientWithWallet();
    [, , $secondUser] = clientWithWallet();

    $this->actingAs($this->admin)
        ->post(route('admin.announcements.store'), announcementPayload([
            'audience' => AnnouncementAudience::Clients->value,
        ]))
        ->assertRedirect();

    $announcement = Announcement::query()->sole();

    expect($announcement->status)->toBe(AnnouncementStatus::Sent)
        ->and($announcement->recipient_count)->toBe(2)
        ->and(AnnouncementRecipient::query()->count())->toBe(2)
        ->and($firstUser->notifications()->count())->toBe(1)
        ->and($secondUser->notifications()->count())->toBe(1)
        ->and($this->admin->notifications()->count())->toBe(0)
        ->and($firstClient->id)->not->toBeNull();
});

it('delivers to one tenant only', function (): void {
    [$client, , $insideUser] = clientWithWallet();
    [, , $outsideUser] = clientWithWallet();

    $this->actingAs($this->admin)
        ->post(route('admin.announcements.store'), announcementPayload([
            'audience' => AnnouncementAudience::Client->value,
            'client_id' => $client->id,
        ]))
        ->assertRedirect();

    expect(Announcement::query()->sole()->recipient_count)->toBe(1)
        ->and($insideUser->notifications()->count())->toBe(1)
        ->and($outsideUser->notifications()->count())->toBe(0);
});

it('delivers to one role only', function (): void {
    $finance = platformUser(Role::FinanceOfficer);
    $compliance = platformUser(Role::ComplianceOfficer);
    $clientUser = clientWithWallet()[2];

    $this->actingAs($this->admin)
        ->post(route('admin.announcements.store'), announcementPayload([
            'audience' => AnnouncementAudience::Role->value,
            'role' => Role::FinanceOfficer->value,
        ]))
        ->assertRedirect();

    expect($finance->notifications()->count())->toBe(1)
        ->and($compliance->notifications()->count())->toBe(0)
        ->and($clientUser->notifications()->count())->toBe(0);
});

it('emails the copy only when the composer asks and the account wants it', function (): void {
    Notification::fake();

    [$client, , $wantsEmail] = clientWithWallet();
    $inAppOnly = User::factory()->forClient($client)->create();

    NotificationPreference::query()->create([
        'user_id' => $wantsEmail->id,
        'category' => NotificationCategory::Platform->value,
        'in_app' => true,
        'email' => true,
    ]);

    $this->actingAs($this->admin)
        ->post(route('admin.announcements.store'), announcementPayload([
            'audience' => AnnouncementAudience::Client->value,
            'client_id' => $client->id,
            'send_email' => true,
        ]))
        ->assertRedirect();

    Notification::assertSentTo(
        $wantsEmail,
        OmniCastNotification::class,
        fn (OmniCastNotification $notification): bool => $notification->title === 'Planned maintenance',
    );

    Notification::assertNotSentTo($inAppOnly, OmniCastNotification::class);

    expect(AnnouncementRecipient::query()->where('user_id', $wantsEmail->id)->sole()->emailed_at)->not->toBeNull()
        ->and(AnnouncementRecipient::query()->where('user_id', $inAppOnly->id)->sole()->emailed_at)->toBeNull()
        ->and($inAppOnly->notifications()->count())->toBe(1);

    $this->actingAs($this->admin)
        ->post(route('admin.announcements.store'), announcementPayload([
            'audience' => AnnouncementAudience::Client->value,
            'client_id' => $client->id,
            'title' => 'No email copy',
            'send_email' => false,
        ]))
        ->assertRedirect();

    Notification::assertSentToTimes($wantsEmail, OmniCastNotification::class, 1);
});

it('refuses an announcement addressed to a client with no tenant', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.announcements.store'), announcementPayload([
            'audience' => AnnouncementAudience::Client->value,
        ]))
        ->assertSessionHasErrors('client_id');

    expect(Announcement::query()->count())->toBe(0);
});

it('will not deliver the same announcement twice', function (): void {
    [$client, , $user] = clientWithWallet();

    $announcement = Announcement::factory()->forClient($client)->create([
        'title' => 'One time only',
    ]);

    $dispatcher = app(AnnouncementDispatcher::class);
    $dispatcher->deliver($announcement);
    $dispatcher->deliver($announcement->refresh());

    expect($announcement->refresh()->status)->toBe(AnnouncementStatus::Sent)
        ->and($announcement->recipient_count)->toBe(1)
        ->and(AnnouncementRecipient::query()->count())->toBe(1)
        ->and($user->notifications()->count())->toBe(1);
});

it('deletes an announcement with its read receipts', function (): void {
    [$client, , $user] = clientWithWallet();

    $announcement = Announcement::factory()->forClient($client)->create();
    app(AnnouncementDispatcher::class)->deliver($announcement);

    $this->actingAs($this->admin)
        ->delete(route('admin.announcements.destroy', ['announcement' => $announcement->id]))
        ->assertRedirect()
        ->assertSessionHas('toast');

    expect(Announcement::query()->count())->toBe(0)
        ->and(AnnouncementRecipient::query()->count())->toBe(0)
        ->and($user->notifications()->count())->toBe(1);
});

it('sends a saved draft only when it is told to', function (): void {
    [$client, , $user] = clientWithWallet();

    $announcement = Announcement::factory()->forClient($client)->create([
        'status' => AnnouncementStatus::Draft,
    ]);

    expect($user->notifications()->count())->toBe(0);

    $this->actingAs($this->admin)
        ->post(route('admin.announcements.send', ['announcement' => $announcement->id]))
        ->assertRedirect()
        ->assertSessionHas('toast');

    expect($announcement->refresh()->status)->toBe(AnnouncementStatus::Sent)
        ->and($user->notifications()->count())->toBe(1);

    $this->actingAs($this->admin)
        ->post(route('admin.announcements.send', ['announcement' => $announcement->id]))
        ->assertRedirect()
        ->assertSessionHas('toast', fn (array $toast): bool => $toast['type'] === 'error');

    expect($user->notifications()->count())->toBe(1);
});
