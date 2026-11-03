<?php

namespace Database\Factories;

use App\Enums\ContactConsentStatus;
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
            'consent_status' => ContactConsentStatus::OptedIn,
            'is_do_not_contact' => false,
        ];
    }

    /**
     * Indicate that the contact has opted out of broadcasts.
     */
    public function optedOut(): static
    {
        return $this->state(fn (array $attributes) => [
            'consent_status' => ContactConsentStatus::OptedOut,
            'opted_out_at' => now(),
        ]);
    }

    /**
     * Indicate that the contact is flagged as do not contact.
     */
    public function doNotContact(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_do_not_contact' => true,
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
