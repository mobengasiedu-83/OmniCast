<?php

namespace Database\Factories;

use App\Enums\SenderIdStatus;
use App\Models\Client;
use App\Models\SenderId;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SenderId>
 */
class SenderIdFactory extends Factory
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
            'name' => Str::upper(fake()->unique()->bothify('BRAND##')),
            'country' => 'GH',
            'purpose' => fake()->sentence(6),
            'status' => SenderIdStatus::Pending,
            'requested_at' => now(),
        ];
    }

    /**
     * Indicate that the sender ID has been approved by compliance.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SenderIdStatus::Approved,
            'submitted_at' => now(),
            'approved_at' => now(),
        ]);
    }
}
