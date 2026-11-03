<?php

namespace Database\Factories;

use App\Enums\ClientService;
use App\Models\Client;
use App\Models\ClientEntitlement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientEntitlement>
 */
class ClientEntitlementFactory extends Factory
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
            'service' => ClientService::Sms,
            'is_enabled' => true,
            'monthly_quota' => null,
            'daily_quota' => null,
            'notes' => null,
        ];
    }

    /**
     * Indicate that the service is turned off for the tenant.
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_enabled' => false,
        ]);
    }
}
