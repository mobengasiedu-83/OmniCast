<?php

namespace App\Services\Notifications;

use App\Enums\NotificationCategory;
use App\Models\NotificationPreference;
use App\Models\User;

/**
 * Resolves the channels an account wants for a category.
 *
 * Preferences are stored sparsely: a row exists only once the account has
 * saved a choice, and until then the defaults in `config/notifications.php`
 * decide. That keeps a newly added category on for every account instead of
 * silently muted because no row was written for it.
 */
class NotificationPreferences
{
    /**
     * The channel map for every category, defaults applied.
     *
     * Read once per delivery so a message never consults the database twice for
     * the same account: the row the dispatcher sees for in-app is the row it
     * sees for email and SMS.
     *
     * @return array<string, array{in_app: bool, email: bool, sms: bool}>
     */
    public function forUser(User $user): array
    {
        $stored = NotificationPreference::query()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy(fn (NotificationPreference $preference): string => $preference->category->value);

        $preferences = [];

        foreach (NotificationCategory::cases() as $category) {
            $defaults = $this->defaults($category);
            $row = $stored->get($category->value);

            $preferences[$category->value] = [
                'in_app' => $row?->in_app ?? $defaults['in_app'],
                'email' => $row?->email ?? $defaults['email'],
                'sms' => $row?->sms ?? $defaults['sms'],
            ];
        }

        return $preferences;
    }

    /**
     * The channels one account wants for one category, defaults applied.
     *
     * @return array{in_app: bool, email: bool, sms: bool}
     */
    public function channelsFor(User $user, NotificationCategory $category): array
    {
        $row = NotificationPreference::query()
            ->where('user_id', $user->id)
            ->where('category', $category->value)
            ->first();

        if ($row instanceof NotificationPreference) {
            return [
                'in_app' => $row->allows('in_app'),
                'email' => $row->allows('email'),
                'sms' => $row->allows('sms'),
            ];
        }

        return $this->defaults($category);
    }

    /**
     * Whether the account wants one channel for one category.
     */
    public function allows(User $user, NotificationCategory $category, string $channel): bool
    {
        return (bool) ($this->channelsFor($user, $category)[$channel] ?? false);
    }

    /**
     * Store the choices the account submitted.
     *
     * A channel the form did not send keeps its current value, so a client that
     * only knows about the older channels cannot silently clear SMS.
     *
     * @param  array<string, array{in_app?: mixed, email?: mixed, sms?: mixed}>  $preferences
     */
    public function update(User $user, array $preferences): void
    {
        foreach (NotificationCategory::cases() as $category) {
            $values = $preferences[$category->value] ?? null;

            if (! is_array($values)) {
                continue;
            }

            $existing = NotificationPreference::query()
                ->where('user_id', $user->id)
                ->where('category', $category->value)
                ->first();

            $defaults = $this->defaults($category);

            NotificationPreference::query()->updateOrCreate(
                ['user_id' => $user->id, 'category' => $category->value],
                [
                    'in_app' => array_key_exists('in_app', $values)
                        ? filter_var($values['in_app'], FILTER_VALIDATE_BOOL)
                        : ($existing?->allows('in_app') ?? $defaults['in_app']),
                    'email' => array_key_exists('email', $values)
                        ? filter_var($values['email'], FILTER_VALIDATE_BOOL)
                        : ($existing?->allows('email') ?? $defaults['email']),
                    'sms' => array_key_exists('sms', $values)
                        ? filter_var($values['sms'], FILTER_VALIDATE_BOOL)
                        : ($existing?->allows('sms') ?? $defaults['sms']),
                ],
            );
        }
    }

    /**
     * The configured defaults for one category.
     *
     * @return array{in_app: bool, email: bool, sms: bool}
     */
    public function defaults(NotificationCategory $category): array
    {
        $configured = (array) config('notifications.preferences.'.$category->value, []);

        return [
            'in_app' => (bool) ($configured['in_app'] ?? true),
            'email' => (bool) ($configured['email'] ?? false),
            'sms' => (bool) ($configured['sms'] ?? false),
        ];
    }
}
