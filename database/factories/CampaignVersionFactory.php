<?php

namespace Database\Factories;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\CampaignVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CampaignVersion>
 */
class CampaignVersionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $snapshot = [
            'name' => fake()->sentence(3),
            'channel' => 'sms',
            'sms_message' => fake()->sentence(12),
            'recipients' => 0,
        ];

        return [
            'campaign_id' => Campaign::factory(),
            'version' => 1,
            'status' => CampaignStatus::Approved,
            'fingerprint' => hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR)),
            'snapshot' => $snapshot,
            'audience_size' => 0,
            'estimated_cost' => 0,
            'frozen_at' => now(),
        ];
    }

    /**
     * Indicate that the snapshot records a submitted campaign.
     */
    public function submitted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CampaignStatus::PendingApproval,
        ]);
    }
}
