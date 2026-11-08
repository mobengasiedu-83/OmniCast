<?php

namespace Database\Factories;

use App\Enums\AutomationRunStatus;
use App\Enums\AutomationTrigger;
use App\Models\AutomationRun;
use App\Models\AutomationWorkflow;
use App\Models\Client;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AutomationRun>
 */
class AutomationRunFactory extends Factory
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
            'client_id' => Client::factory(),
            'contact_id' => Contact::factory(),
            'campaign_id' => null,
            'trigger' => AutomationTrigger::ContactCreated,
            'status' => AutomationRunStatus::Queued,
            'current_position' => 1,
            'context' => null,
            'detail' => null,
            'resume_at' => null,
            'started_at' => null,
            'finished_at' => null,
        ];
    }

    /**
     * Indicate the run has already finished.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AutomationRunStatus::Completed,
            'started_at' => now()->subMinutes(5),
            'finished_at' => now(),
        ]);
    }

    /**
     * Indicate the run stopped on a failure.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AutomationRunStatus::Failed,
            'detail' => 'The provider rejected the message.',
            'started_at' => now()->subMinutes(5),
            'finished_at' => now(),
        ]);
    }
}
