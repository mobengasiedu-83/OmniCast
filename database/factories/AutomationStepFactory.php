<?php

namespace Database\Factories;

use App\Enums\AutomationAction;
use App\Models\AutomationStep;
use App\Models\AutomationWorkflow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AutomationStep>
 */
class AutomationStepFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'automation_workflow_id' => AutomationWorkflow::factory(),
            'position' => fake()->numberBetween(1, 5),
            'action' => AutomationAction::Wait,
            'label' => null,
            'config' => ['minutes' => 60],
        ];
    }

    /**
     * Indicate the step performs the given action at the given position.
     *
     * @param  array<string, mixed>  $config
     */
    public function action(AutomationAction $action, int $position = 1, array $config = []): static
    {
        return $this->state(fn (array $attributes): array => [
            'action' => $action,
            'position' => $position,
            'config' => $config,
        ]);
    }
}
