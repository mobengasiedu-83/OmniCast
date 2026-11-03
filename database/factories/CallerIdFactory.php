<?php

namespace Database\Factories;

use App\Enums\SenderIdStatus;
use App\Models\CallerId;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CallerId>
 */
class CallerIdFactory extends Factory
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
            'number' => '+233'.fake()->unique()->numerify('#########'),
            'label' => fake()->company(),
            'sip_trunk' => 'trunk-'.fake()->numerify('##'),
            'status' => SenderIdStatus::Pending,
            'requested_at' => now(),
        ];
    }
}
