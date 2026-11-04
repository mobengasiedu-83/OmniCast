<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\ContactSegment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactSegment>
 */
class ContactSegmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['client_id' => Client::factory(), 'name' => fake()->unique()->words(2, true), 'filters' => []];
    }
}
