<?php

use App\Enums\AnnouncementAudience;
use App\Enums\NotificationCategory;
use App\Models\Announcement;
use App\Models\AnnouncementRecipient;
use App\Models\NotificationPreference;
use App\Notifications\OmniCastNotification;
use App\Services\Notifications\AnnouncementDispatcher;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\NotificationPreferences;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();

    [$this->client, $this->wallet, $this->user] = clientWithWallet();
});

it('shows the configured defaults before the account saves anything', function (): void {
    $this->actingAs($this->user)
        ->get(route('notifications.preferences.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/notifications')
            ->where('preferences.campaigns.in_app', true)
            ->where('preferences.campaigns.email', false)
            ->where('preferences.billing.in_app', true)
            ->where('preferences.billing.email', true)
            ->has('categories', count(NotificationCategory::cases()))
            ->has('channels', 2));

    expect(NotificationPreference::query()->count())->toBe(0);
});

it('stops a muted category reaching the inbox', function (): void {
    $this->actingAs($this->user)
        ->put(route('notifications.preferences.update'), [
            'preferences' => [
                'campaigns' => ['in_app' => false, 'email' => false],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('toast');

    expect(NotificationPreference::query()->sole()->in_app)->toBeFalse();

    $delivery = app(NotificationDispatcher::class)->send(
        $this->user,
        NotificationCategory::Campaigns,
        'Campaign completed',
        'The message body.',
    );

    expect($delivery)->toBeNull()
        ->and($this->user->notifications()->count())->toBe(0);
});

it('sends the email copy when the account switches the email channel on', function (): void {
    Notification::fake();

    $this->actingAs($this->user)
        ->put(route('notifications.preferences.update'), [
            'preferences' => [
                'campaigns' => ['in_app' => true, 'email' => true],
            ],
        ])
        ->assertRedirect();

    app(NotificationDispatcher::class)->send(
        $this->user,
        NotificationCategory::Campaigns,
        'Campaign completed',
        'The message body.',
    );

    Notification::assertSentTo(
        $this->user,
        OmniCastNotification::class,
        fn (OmniCastNotification $notification): bool => $notification->title === 'Campaign completed',
    );

    expect($this->user->notifications()->count())->toBe(1);
});

it('refuses a category the centre does not have', function (): void {
    $this->actingAs($this->user)
        ->put(route('notifications.preferences.update'), [
            'preferences' => [
                'bogus' => ['in_app' => true, 'email' => true],
            ],
        ])
        ->assertSessionHasErrors('preferences');

    expect(NotificationPreference::query()->count())->toBe(0);
});

it('keeps a platform announcement in the inbox even when its category is muted', function (): void {
    $this->actingAs($this->user)
        ->put(route('notifications.preferences.update'), [
            'preferences' => [
                'platform' => ['in_app' => false, 'email' => false],
            ],
        ])
        ->assertRedirect();

    $announcement = Announcement::factory()->create([
        'audience' => AnnouncementAudience::Client,
        'client_id' => $this->client->id,
    ]);

    app(AnnouncementDispatcher::class)->deliverTo($announcement, $this->user);

    expect($this->user->notifications()->count())->toBe(1)
        ->and(AnnouncementRecipient::query()->sole()->notification_id)->not->toBeNull();
});

it('offers the SMS column only when the platform switch is on', function (): void {
    $this->actingAs($this->user)
        ->get(route('notifications.preferences.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('channels', 2));

    config()->set('notifications.sms.enabled', true);

    $this->actingAs($this->user)
        ->get(route('notifications.preferences.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('channels', 3)
            ->where('channels.2.value', 'sms')
            ->where('preferences.campaigns.sms', false));
});

it('keeps the SMS opt-in when a later form does not mention it', function (): void {
    $this->actingAs($this->user)
        ->put(route('notifications.preferences.update'), [
            'preferences' => [
                'campaigns' => ['in_app' => true, 'email' => false, 'sms' => true],
            ],
        ])
        ->assertRedirect();

    expect(NotificationPreference::query()->sole()->sms)->toBeTrue();

    $this->actingAs($this->user)
        ->put(route('notifications.preferences.update'), [
            'preferences' => [
                'campaigns' => ['in_app' => false, 'email' => true],
            ],
        ])
        ->assertRedirect();

    $preference = NotificationPreference::query()->sole();

    expect($preference->sms)->toBeTrue()
        ->and($preference->in_app)->toBeFalse()
        ->and($preference->email)->toBeTrue();
});

it('texts the account only when the platform switch and the account both say yes', function (): void {
    $this->user->forceFill(['phone' => '+233201234567'])->save();

    app(NotificationPreferences::class)->update($this->user, [
        'campaigns' => ['in_app' => true, 'email' => false, 'sms' => true],
    ]);

    $blocked = app(NotificationDispatcher::class)->send(
        $this->user,
        NotificationCategory::Campaigns,
        'Campaign completed',
        'The message body.',
    );

    expect($blocked?->texted)->toBeFalse()
        ->and($blocked?->emailed)->toBeFalse()
        ->and($this->user->notifications()->count())->toBe(1);

    config()->set('notifications.sms.enabled', true);

    $texted = app(NotificationDispatcher::class)->send(
        $this->user,
        NotificationCategory::Campaigns,
        'Campaign completed',
        'The message body.',
    );

    expect($texted?->texted)->toBeTrue()
        ->and($texted?->emailed)->toBeFalse();
});

it('does not text an account without a phone number', function (): void {
    config()->set('notifications.sms.enabled', true);

    app(NotificationPreferences::class)->update($this->user, [
        'campaigns' => ['in_app' => true, 'email' => false, 'sms' => true],
    ]);

    $delivery = app(NotificationDispatcher::class)->send(
        $this->user,
        NotificationCategory::Campaigns,
        'Campaign completed',
        'The message body.',
    );

    expect($delivery?->texted)->toBeFalse()
        ->and($delivery?->notification)->not->toBeNull();
});
