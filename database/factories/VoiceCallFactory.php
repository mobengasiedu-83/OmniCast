<?php

namespace Database\Factories;

use App\Enums\VoiceCallStatus;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\VoiceCall;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<VoiceCall>
 */
class VoiceCallFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'VC-'.Str::upper(Str::random(12)),
            'client_id' => Client::factory(),
            'campaign_id' => Campaign::factory(),
            'campaign_contact_id' => null,
            'provider' => 'log',
            'to_number' => '+2332'.fake()->numerify('#######'),
            'from_number' => '+233200000000',
            'status' => VoiceCallStatus::Initiated,
            'attempts' => 1,
            'dialled_at' => now(),
            'duration_seconds' => 0,
            'cost' => 0,
        ];
    }

    /**
     * Indicate that the call was picked up and ran to the end.
     */
    public function answered(int $durationSeconds = 42): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => VoiceCallStatus::Completed,
            'dialled_at' => now()->subMinutes(3),
            'ringing_at' => now()->subMinutes(3),
            'answered_at' => now()->subMinutes(3),
            'ended_at' => now()->subMinutes(2),
            'duration_seconds' => $durationSeconds,
        ]);
    }

    /**
     * Indicate that nobody picked the call up.
     */
    public function noAnswer(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => VoiceCallStatus::NoAnswer,
            'dialled_at' => now()->subMinutes(3),
            'ringing_at' => now()->subMinutes(3),
            'ended_at' => now()->subMinutes(2),
            'hangup_cause' => 'NO_ANSWER',
        ]);
    }

    /**
     * Attach the call to the campaign it was dialled for.
     */
    public function forCampaign(Campaign $campaign): static
    {
        return $this->state(fn (array $attributes): array => [
            'client_id' => $campaign->client_id,
            'campaign_id' => $campaign->id,
        ]);
    }
}
