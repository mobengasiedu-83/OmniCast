<?php

namespace App\Services\Support;

use App\Models\PlatformSetting;
use App\Services\PlatformConfiguration;

/**
 * The contact desk's configuration, read and written from one place.
 *
 * Everything an operator can change about the support desk — the address a
 * visitor writes to, the hours the team answers, whether an acknowledgement goes
 * out — lives in `platform_settings` under the `support` group. The public page
 * and the admin screens read the same keys, so a number confirmed by the
 * business is published by saving this screen and nowhere else.
 */
class SupportSettings
{
    /**
     * The settings group.
     */
    public const GROUP = 'support';

    /**
     * The text settings, with the value used when none has been saved.
     *
     * @var array<string, string>
     */
    private const DEFAULTS = [
        'contact_email' => '',
        'support_email' => '',
        'sales_email' => '',
        'phone' => '',
        'whatsapp' => '',
        'office_address' => '',
        'opening_hours' => '',
        'response_time' => '',
        'status_url' => '',
    ];

    /**
     * The switches, with the value used when none has been saved.
     *
     * @var array<string, bool>
     */
    private const SWITCHES = [
        'acknowledge_enquiries' => true,
        'notify_on_status_change' => true,
        'notify_team' => true,
    ];

    public function __construct(protected PlatformConfiguration $configuration) {}

    /**
     * The address the public contact page prints, falling back to the support
     * inbox and then to the platform's own company address.
     */
    public function contactEmail(): ?string
    {
        return $this->text('contact_email')
            ?? $this->text('support_email')
            ?? $this->text('sales_email')
            ?? $this->companyEmail();
    }

    /**
     * The inbox that answers client tickets.
     */
    public function supportEmail(): ?string
    {
        return $this->text('support_email') ?? $this->contactEmail();
    }

    /**
     * The inbox that answers sales enquiries.
     */
    public function salesEmail(): ?string
    {
        return $this->text('sales_email') ?? $this->supportEmail();
    }

    public function phone(): ?string
    {
        return $this->text('phone');
    }

    public function whatsapp(): ?string
    {
        return $this->text('whatsapp');
    }

    public function officeAddress(): ?string
    {
        return $this->text('office_address');
    }

    public function openingHours(): ?string
    {
        return $this->text('opening_hours');
    }

    public function responseTime(): ?string
    {
        return $this->text('response_time');
    }

    public function statusUrl(): ?string
    {
        return $this->text('status_url');
    }

    /**
     * Whether a requester is emailed a reference number straight away.
     */
    public function acknowledgesEnquiries(): bool
    {
        return $this->switch('acknowledge_enquiries');
    }

    /**
     * Whether the requester is emailed when the status changes.
     */
    public function notifiesOnStatusChange(): bool
    {
        return $this->switch('notify_on_status_change');
    }

    /**
     * Whether the owning team is emailed about new conversations and replies.
     */
    public function notifiesTeam(): bool
    {
        return $this->switch('notify_team');
    }

    /**
     * Everything the admin screen binds to, in one array.
     *
     * @return array<string, string|bool>
     */
    public function all(): array
    {
        $values = [];

        foreach (array_keys(self::DEFAULTS) as $key) {
            $values[$key] = $this->text($key) ?? '';
        }

        foreach (array_keys(self::SWITCHES) as $key) {
            $values[$key] = $this->switch($key);
        }

        return $values;
    }

    /**
     * Save the values the admin screen submitted.
     *
     * @param  array<string, mixed>  $values
     */
    public function update(array $values, ?int $userId): void
    {
        foreach (array_keys(self::DEFAULTS) as $key) {
            if (array_key_exists($key, $values)) {
                $this->store($key, trim((string) $values[$key]), $userId);
            }
        }

        foreach (array_keys(self::SWITCHES) as $key) {
            if (array_key_exists($key, $values)) {
                $this->store($key, (bool) $values[$key] ? '1' : '0', $userId);
            }
        }
    }

    /**
     * A text setting, or null when it was never saved or was blanked.
     */
    private function text(string $key): ?string
    {
        $value = trim((string) $this->configuration->resolve(self::GROUP, $key, ''));

        return $value === '' ? null : $value;
    }

    /**
     * A switch, defaulting to its shipped value.
     */
    private function switch(string $key): bool
    {
        return filter_var(
            $this->configuration->resolve(self::GROUP, $key, self::SWITCHES[$key]),
            FILTER_VALIDATE_BOOL,
        );
    }

    /**
     * The company address the platform already publishes.
     */
    private function companyEmail(): ?string
    {
        $value = trim((string) $this->configuration->resolve('company', 'email', ''));

        return $value === '' ? null : $value;
    }

    /**
     * Write one setting, keeping the activity trail on who last changed it.
     */
    private function store(string $key, string $value, ?int $userId): void
    {
        $setting = PlatformSetting::query()->firstOrNew([
            'group' => self::GROUP,
            'key' => $key,
        ]);

        $setting->value = $value;
        $setting->is_secret = false;
        $setting->updated_by = $userId;
        $setting->save();
    }
}
