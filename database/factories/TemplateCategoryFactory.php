<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\TemplateCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TemplateCategory>
 */
class TemplateCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->words(2, true));

        return [
            'client_id' => null,
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(8),
            'sort_order' => fake()->numberBetween(0, 20),
        ];
    }

    /**
     * Indicate that the category is private to a client tenant.
     */
    public function forClient(Client|int $client): static
    {
        return $this->state(fn (array $attributes): array => [
            'client_id' => $client instanceof Client ? $client->id : $client,
        ]);
    }
}
