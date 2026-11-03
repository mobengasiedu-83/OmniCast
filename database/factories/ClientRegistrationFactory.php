<?php

namespace Database\Factories;

use App\Enums\ClientRegistrationStatus;
use App\Enums\ClientService;
use App\Models\ClientRegistration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientRegistration>
 */
class ClientRegistrationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_name' => fake()->unique()->company(),
            'contact_name' => fake()->name(),
            'email' => fake()->unique()->companyEmail(),
            'phone' => '+2332'.fake()->numerify('#######'),
            'country' => fake()->country(),
            'timezone' => 'Africa/Accra',
            'website' => fake()->url(),
            'services' => [ClientService::Sms->value],
            'expected_monthly_volume' => fake()->numberBetween(1000, 50000),
            'message' => fake()->sentence(),
            'status' => ClientRegistrationStatus::Pending,
            'ip_address' => fake()->ipv4(),
        ];
    }

    /**
     * Indicate that the application has already been approved.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ClientRegistrationStatus::Approved,
            'reviewed_at' => now(),
        ]);
    }

    /**
     * Indicate that the application was declined.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ClientRegistrationStatus::Rejected,
            'reviewed_at' => now(),
            'rejection_reason' => 'Incomplete company documentation.',
        ]);
    }
}
