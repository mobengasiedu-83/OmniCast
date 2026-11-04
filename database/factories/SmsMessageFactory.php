<?php

namespace Database\Factories;

use App\Enums\SmsEncoding;
use App\Enums\SmsMessageStatus;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\SmsMessage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SmsMessage>
 */
class SmsMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'SM-'.Str::upper(Str::random(12)),
            'client_id' => Client::factory(),
            'campaign_id' => Campaign::factory(),
            'campaign_contact_id' => null,
            'provider' => 'log',
            'to_number' => '+2332'.fake()->numerify('#######'),
            'from_number' => 'OMNICAST',
            'body' => 'Your statement is ready.',
            'encoding' => SmsEncoding::Gsm7,
            'segments' => 1,
            'status' => SmsMessageStatus::Submitted,
            'attempts' => 1,
            'submitted_at' => now(),
            'cost' => 0,
        ];
    }

    /**
     * Indicate that the network confirmed the handset received the message.
     */
    public function delivered(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SmsMessageStatus::Delivered,
            'provider_status' => 'DELIVRD',
            'submitted_at' => now()->subMinutes(2),
            'delivered_at' => now()->subMinute(),
        ]);
    }

    /**
     * Indicate that the network could not deliver the message.
     */
    public function undelivered(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SmsMessageStatus::Undelivered,
            'provider_status' => 'UNDELIV',
            'submitted_at' => now()->subMinutes(2),
            'failed_at' => now()->subMinute(),
            'error_message' => 'The recipient handset is switched off.',
        ]);
    }

    /**
     * Indicate that the provider rejected the message outright.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SmsMessageStatus::Failed,
            'submitted_at' => now()->subMinutes(2),
            'failed_at' => now()->subMinute(),
            'error_message' => 'The gateway rejected the message.',
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
