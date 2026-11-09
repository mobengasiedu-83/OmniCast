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
     * @return array<string, array{in_app: bool, email: bool}>
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
            ];
        }

        return $preferences;
    }

    /**
     * Whether the account wants one channel for one category.
     */
    public function allows(User $user, NotificationCategory $category, string $channel): bool
    {
        $row = NotificationPreference::query()
            ->where('user_id', $user->id)
            ->where('category', $category->value)
            ->first();

        return $row?->allows($channel) ?? (bool) ($this->defaults($category)[$channel] ?? false);
    }

    /**
     * Store the choices the account submitted.
     *
     * @param  array<string, array{in_app?: mixed, email?: mixed}>  $preferences
     */
    public function update(User $user, array $preferences): void
    {
        foreach (NotificationCategory::cases() as $category) {
            $values = $preferences[$category->value] ?? null;

            if (! is_array($values)) {
                continue;
            }

            NotificationPreference::query()->updateOrCreate(
                ['user_id' => $user->id, 'category' => $category->value],
                [
                    'in_app' => filter_var($values['in_app'] ?? false, FILTER_VALIDATE_BOOL),
                    'email' => filter_var($values['email'] ?? false, FILTER_VALIDATE_BOOL),
                ],
            );
        }
    }

    /**
     * The configured defaults for one category.
     *
     * @return array{in_app: bool, email: bool}
     */
    public function defaults(NotificationCategory $category): array
    {
        $configured = (array) config('notifications.preferences.'.$category->value, []);

        return [
            'in_app' => (bool) ($configured['in_app'] ?? true),
            'email' => (bool) ($configured['email'] ?? false),
        ];
    }
}
