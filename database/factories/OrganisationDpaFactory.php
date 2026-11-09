<?php

namespace Database\Factories;

use App\Enums\OrganisationDpaStatus;
use App\Models\Client;
use App\Models\OrganisationDpa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganisationDpa>
 */
class OrganisationDpaFactory extends Factory
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
            'status' => OrganisationDpaStatus::NotStarted,
            'signatory_name' => null,
            'signatory_email' => null,
            'requested_at' => null,
            'signed_at' => null,
            'notes' => null,
        ];
    }

    /**
     * A request the platform team has not answered yet.
     */
    public function requested(): static
    {
        return $this->state(fn (): array => [
            'status' => OrganisationDpaStatus::Requested,
            'requested_at' => now(),
        ]);
    }

    /**
     * A countersigned agreement.
     */
    public function signed(): static
    {
        return $this->state(fn (): array => [
            'status' => OrganisationDpaStatus::Signed,
            'signatory_name' => $this->faker->name(),
            'signatory_email' => $this->faker->safeEmail(),
            'requested_at' => now()->subWeek(),
            'signed_at' => now(),
        ]);
    }
}
