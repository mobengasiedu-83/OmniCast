<?php

namespace Database\Factories;

use App\Enums\Channel;
use App\Models\Client;
use App\Models\RateCard;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RateCard>
 */
class RateCardFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'name' => fake()->words(2, true).' Card',
            'channel' => Channel::Sms,
            'currency' => 'GHS',
            'is_default' => true,
            'effective_from' => now()->startOfMonth(),
            'effective_to' => null,
        ];
    }

    /**
     * Indicate that the rate card is the platform-wide default.
     */
    public function platformDefault(): static
    {
        return $this->state(fn (array $attributes) => [
            'client_id' => null,
        ]);
    }
}
