<?php

namespace Database\Factories;

use App\Enums\CampaignStatus;
use App\Enums\Channel;
use App\Enums\SmsEncoding;
use App\Models\Campaign;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Campaign>
 */
class CampaignFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'CMP-'.Str::upper(fake()->unique()->bothify('####??')),
            'client_id' => Client::factory(),
            'name' => ucfirst(fake()->words(3, true)),
            'channel' => Channel::Sms,
            'status' => CampaignStatus::Draft,
            'sms_message' => fake()->sentence(12),
            'sms_encoding' => SmsEncoding::Gsm7,
            'estimated_recipients' => 0,
            'estimated_sms_parts' => 1,
            'estimated_cost' => 0,
            'reserved_amount' => 0,
            'actual_cost' => 0,
            'schedule_type' => 'now',
            'timezone' => 'Africa/Accra',
        ];
    }

    /**
     * Indicate that the campaign is waiting for a compliance decision.
     */
    public function pendingApproval(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CampaignStatus::PendingApproval,
            'submitted_at' => now(),
        ]);
    }

    /**
     * Indicate that the campaign has been approved for launch.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CampaignStatus::Approved,
            'submitted_at' => now(),
            'approved_at' => now(),
        ]);
    }

    /**
     * Attach the campaign to the given client tenant.
     */
    public function forClient(Client $client): static
    {
        return $this->state(fn (array $attributes) => [
            'client_id' => $client->id,
        ]);
    }
}
