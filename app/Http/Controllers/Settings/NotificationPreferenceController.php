<?php

namespace App\Http\Controllers\Settings;

use App\Enums\NotificationCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\NotificationPreferenceRequest;
use App\Models\User;
use App\Services\Notifications\NotificationPreferences;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Which categories reach an account, and on which channel.
 *
 * The screen is a matrix of categories against in-app, email and, when the
 * platform switches it on, SMS, stored in `notification_preferences` with the
 * defaults in `config/notifications.php` filling in whatever the account has not
 * chosen. Muting a category silences this account only: the platform still
 * records the event, and another account of the same tenant keeps receiving it.
 */
class NotificationPreferenceController extends Controller
{
    /**
     * Show the caller's matrix, defaults applied.
     */
    public function edit(Request $request, NotificationPreferences $preferences): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('settings/notifications', [
            'preferences' => $preferences->forUser($user),
            'categories' => NotificationCategory::catalogue(),
            'channels' => $this->channels(),
        ]);
    }

    /**
     * The channel columns the screen offers.
     *
     * SMS is only a column when the platform switched the channel on: an account
     * cannot opt into a copy the platform will never dial.
     *
     * @return list<array{value: string, label: string, description: string}>
     */
    protected function channels(): array
    {
        $channels = [
            [
                'value' => 'in_app',
                'label' => 'In-app',
                'description' => 'The bell in the header and the notifications screen.',
            ],
            [
                'value' => 'email',
                'label' => 'Email',
                'description' => 'A copy to the address on your account.',
            ],
        ];

        if ((bool) config('notifications.sms.enabled', false)) {
            $channels[] = [
                'value' => 'sms',
                'label' => 'SMS',
                'description' => 'A short copy to the phone number on your account.',
            ];
        }

        return $channels;
    }

    /**
     * Save the matrix.
     */
    public function update(NotificationPreferenceRequest $request, NotificationPreferences $preferences): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $preferences->update($user, $request->preferences());

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Your notification preferences were saved.',
        ]);
    }
}
