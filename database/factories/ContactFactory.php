<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
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
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone' => '+233'.fake()->unique()->numerify('#########'),
            'email' => fake()->safeEmail(),
            'company' => fake()->company(),
            'account_number' => fake()->numerify('ACC-######'),
            'location' => fake()->city(),
            'language' => 'en',
            'timezone' => 'Africa/Accra',
            'dnd' => false,
        ];
    }

    /**
     * Indicate that the contact asked not to be disturbed.
     */
    public function dnd(): static
    {
        return $this->state(fn (array $attributes) => [
            'dnd' => true,
            'dnd_at' => now(),
        ]);
    }

    /**
     * Attach the contact to the given client tenant.
     */
    public function forClient(Client $client): static
    {
        return $this->state(fn (array $attributes) => [
            'client_id' => $client->id,
        ]);
    }
}
