<?php

namespace Database\Factories;

use App\Enums\EmailFormat;
use App\Enums\EmailMessageStatus;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\EmailMessage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EmailMessage>
 */
class EmailMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'EM-'.Str::upper(Str::random(12)),
            'client_id' => Client::factory(),
            'campaign_id' => Campaign::factory(),
            'campaign_contact_id' => null,
            'provider' => 'log',
            'to_address' => fake()->unique()->safeEmail(),
            'from_address' => 'noreply@omnicast.gh',
            'from_name' => 'OmniCast',
            'subject' => fake()->sentence(6),
            'body' => '<p>Your statement is ready.</p>',
            'format' => EmailFormat::Html,
            'status' => EmailMessageStatus::Submitted,
            'attempts' => 1,
            'submitted_at' => now(),
            'cost' => 0,
        ];
    }

    /**
     * Indicate that the mailbox confirmed delivery.
     */
    public function delivered(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => EmailMessageStatus::Delivered,
            'provider_status' => 'delivered',
            'submitted_at' => now()->subMinutes(2),
            'delivered_at' => now()->subMinute(),
        ]);
    }

    /**
     * Indicate that the mailbox bounced the message.
     */
    public function bounced(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => EmailMessageStatus::Bounced,
            'provider_status' => 'bounced',
            'submitted_at' => now()->subMinutes(2),
            'bounced_at' => now()->subMinute(),
            'failed_at' => now()->subMinute(),
            'error_message' => 'The mailbox does not exist.',
        ]);
    }

    /**
     * Indicate that the recipient marked the message as spam.
     */
    public function complained(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => EmailMessageStatus::Complained,
            'provider_status' => 'complaint',
            'submitted_at' => now()->subMinutes(2),
            'failed_at' => now()->subMinute(),
            'error_message' => 'The recipient marked this message as spam.',
        ]);
    }

    /**
     * Indicate that the transport rejected the message outright.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => EmailMessageStatus::Failed,
            'submitted_at' => now()->subMinutes(2),
            'failed_at' => now()->subMinute(),
            'error_message' => 'The mail server rejected the message.',
        ]);
    }

    /**
     * Attach the message to the campaign it was submitted for.
     */
    public function forCampaign(Campaign $campaign): static
    {
        return $this->state(fn (array $attributes): array => [
            'client_id' => $campaign->client_id,
            'campaign_id' => $campaign->id,
        ]);
    }
}
