<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
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
            'name' => ucfirst(fake()->unique()->word()),
            'color' => fake()->randomElement(['slate', 'emerald', 'amber', 'sky', 'rose']),
        ];
    }
}
