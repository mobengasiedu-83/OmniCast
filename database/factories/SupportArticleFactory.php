<?php

namespace Database\Factories;

use App\Models\SupportArticle;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SupportArticle>
 */
class SupportArticleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = Str::title($this->faker->unique()->sentence(4));

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'category' => 'Getting started',
            'excerpt' => $this->faker->sentence(),
            'body' => "## Overview\n\n".$this->faker->paragraphs(3, true),
            'is_published' => false,
            'is_featured' => false,
            'sort_order' => $this->faker->numberBetween(0, 10),
            'views' => 0,
            'published_at' => null,
        ];
    }

    /**
     * An article the help centre shows.
     */
    public function published(): static
    {
        return $this->state(fn (): array => [
            'is_published' => true,
            'published_at' => now()->subWeek(),
        ]);
    }

    /**
     * An article pinned to the top of the library.
     */
    public function featured(): static
    {
        return $this->state(fn (): array => [
            'is_published' => true,
            'is_featured' => true,
            'published_at' => now()->subWeek(),
        ]);
    }
}
