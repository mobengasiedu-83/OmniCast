<?php

namespace Database\Factories;

use App\Enums\SupportCategoryAudience;
use App\Enums\SupportPriority;
use App\Models\SupportCategory;
use App\Models\SupportTeam;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SupportCategory>
 */
class SupportCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title($this->faker->unique()->words(2, true));

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => $this->faker->sentence(),
            'audience' => SupportCategoryAudience::Both,
            'support_team_id' => null,
            'default_priority' => SupportPriority::Normal,
            'is_active' => true,
            'sort_order' => $this->faker->numberBetween(0, 10),
        ];
    }

    /**
     * A category offered only on the public contact form.
     */
    public function contactOnly(): static
    {
        return $this->state(fn (): array => ['audience' => SupportCategoryAudience::Contact]);
    }

    /**
     * A category offered only on the client ticket form.
     */
    public function ticketOnly(): static
    {
        return $this->state(fn (): array => ['audience' => SupportCategoryAudience::Ticket]);
    }

    /**
     * Route the category to a team.
     */
    public function forTeam(SupportTeam $team): static
    {
        return $this->state(fn (): array => ['support_team_id' => $team->id]);
    }
}
