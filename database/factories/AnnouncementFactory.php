<?php

namespace Database\Factories;

use App\Enums\AnnouncementAudience;
use App\Enums\AnnouncementSeverity;
use App\Enums\AnnouncementStatus;
use App\Enums\NotificationCategory;
use App\Enums\Role;
use App\Models\Announcement;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(5),
            'body' => fake()->paragraph(),
            'category' => NotificationCategory::Platform,
            'audience' => AnnouncementAudience::Everyone,
            'client_id' => null,
            'role' => null,
            'severity' => AnnouncementSeverity::Info,
            'send_email' => false,
            'status' => AnnouncementStatus::Draft,
            'sent_at' => null,
            'recipient_count' => 0,
            'read_count' => 0,
        ];
    }

    /**
     * Indicate that the announcement has already gone out.
     */
    public function sent(int $recipients = 0): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AnnouncementStatus::Sent,
            'sent_at' => now(),
            'recipient_count' => $recipients,
        ]);
    }

    /**
     * Address the announcement to one tenant.
     */
    public function forClient(Client $client): static
    {
        return $this->state(fn (array $attributes): array => [
            'audience' => AnnouncementAudience::Client,
            'client_id' => $client->id,
            'role' => null,
        ]);
    }

    /**
     * Address the announcement to every account holding a role.
     */
    public function forRole(Role $role): static
    {
        return $this->state(fn (array $attributes): array => [
            'audience' => AnnouncementAudience::Role,
            'client_id' => null,
            'role' => $role->value,
        ]);
    }

    /**
     * Ask for the email copy as well as the inbox row.
     */
    public function emailing(): static
    {
        return $this->state(fn (array $attributes): array => ['send_email' => true]);
    }

    /**
     * Use a louder severity than the default.
     */
    public function critical(): static
    {
        return $this->state(fn (array $attributes): array => [
            'severity' => AnnouncementSeverity::Critical,
        ]);
    }
}
