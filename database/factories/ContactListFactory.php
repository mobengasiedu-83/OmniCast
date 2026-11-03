<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\ContactList;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactList>
 */
class ContactListFactory extends Factory
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
            'name' => ucfirst(fake()->unique()->words(2, true)),
            'description' => fake()->sentence(),
        ];
    }

    /**
     * Attach the list to the given client tenant.
     */
    public function forClient(Client $client): static
    {
        return $this->state(fn (array $attributes) => [
            'client_id' => $client->id,
        ]);
    }
}
