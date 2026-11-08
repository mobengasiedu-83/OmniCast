<?php

namespace Database\Factories;

use App\Enums\AutomationTrigger;
use App\Models\AutomationWorkflow;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AutomationWorkflow>
 */
class AutomationWorkflowFactory extends Factory
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
            'name' => fake()->words(3, true),
            'description' => null,
            'trigger' => AutomationTrigger::ContactCreated,
            'trigger_config' => null,
            'is_active' => true,
            'runs_count' => 0,
        ];
    }

    /**
     * Indicate the workflow fires on the given trigger.
     *
     * @param  array<string, mixed>|null  $config
     */
    public function triggeredBy(AutomationTrigger $trigger, ?array $config = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'trigger' => $trigger,
            'trigger_config' => $config,
        ]);
    }

    /**
     * Indicate the workflow has been switched off.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }
}
