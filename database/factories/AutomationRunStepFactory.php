<?php

namespace Database\Factories;

use App\Enums\AutomationAction;
use App\Enums\AutomationStepStatus;
use App\Models\AutomationRun;
use App\Models\AutomationRunStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AutomationRunStep>
 */
class AutomationRunStepFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'automation_run_id' => AutomationRun::factory(),
            'automation_step_id' => null,
            'position' => 1,
            'action' => AutomationAction::SendSms,
            'status' => AutomationStepStatus::Completed,
            'detail' => null,
            'payload' => null,
            'ran_at' => now(),
        ];
    }

    /**
     * Indicate the step was not needed for this contact.
     */
    public function skipped(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AutomationStepStatus::Skipped,
            'detail' => 'The contact has no email address.',
        ]);
    }
}
