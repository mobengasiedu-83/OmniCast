<?php

namespace Database\Factories;

use App\Enums\ClientStatus;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'code' => Str::upper(fake()->unique()->bothify('CL-####??')),
            'email' => fake()->unique()->companyEmail(),
            'phone' => '+2332'.fake()->numerify('#######'),
            'timezone' => 'Africa/Accra',
            'status' => ClientStatus::Active,
            'credit_limit' => 5000,
            'notes' => null,
        ];
    }

    /**
     * Indicate that the client is suspended and may not broadcast.
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ClientStatus::Suspended,
            'suspended_at' => now(),
            'status_changed_at' => now(),
        ]);
    }

    /**
     * Indicate that the client has been approved but is not yet activated.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ClientStatus::Pending,
            'status_changed_at' => now(),
        ]);
    }

    /**
     * Indicate that the client relationship has been closed.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ClientStatus::Archived,
            'archived_at' => now(),
            'status_changed_at' => now(),
        ]);
    }
}
