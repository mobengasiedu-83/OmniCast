<?php

namespace App\Services\Automation;

use App\Enums\AutomationRunStatus;
use App\Enums\AutomationTrigger;
use App\Jobs\RunAutomationStep;
use App\Models\AutomationRun;
use App\Models\AutomationWorkflow;
use App\Models\Client;
use App\Models\Contact;
use App\Services\PlatformConfiguration;
use Illuminate\Support\Collection;

/**
 * Decides which workflows an event enrols a contact into.
 *
 * The engine is the only place that writes a run, so a trigger fired from the
 * contact book, the campaign pipeline or the scheduler always behaves the same
 * way: match the workflow, write the run, hand the run to the queue.
 */
class AutomationEngine
{
    public function __construct(private readonly PlatformConfiguration $configuration) {}

    /**
     * Whether automations may run for the given tenant.
     */
    public function enabled(?Client $client = null): bool
    {
        return (bool) config('automations.enabled')
            && $this->configuration->enabled('automations', true, $client);
    }

    /**
     * Fire a trigger and enrol the subject in every matching workflow.
     *
     * @param  array<string, mixed>  $context
     * @return int the number of runs that were started
     */
    public function emit(AutomationTrigger $trigger, ?Contact $contact = null, array $context = []): int
    {
        $clientId = $contact?->client_id ?? ($context['client_id'] ?? null);

        if (! is_int($clientId) || ! $this->enabled(Client::query()->find($clientId))) {
            return 0;
        }

        if ($trigger->needsContact() && $contact === null) {
            return 0;
        }

        $started = 0;

        foreach ($this->workflowsFor($trigger, $clientId) as $workflow) {
            if ($contact !== null && ! $this->matches($workflow, $contact)) {
                continue;
            }

            if ($this->start($workflow, $contact, $context) !== null) {
                $started++;
            }
        }

        return $started;
    }

    /**
     * Start one run of a workflow, or nothing when the workflow cannot run.
     *
     * @param  array<string, mixed>  $context
     */
    public function start(AutomationWorkflow $workflow, ?Contact $contact = null, array $context = []): ?AutomationRun
    {
        if (! $workflow->is_active || ! $this->enabled($workflow->client)) {
            return null;
        }

        if ($workflow->steps()->doesntExist()) {
            return null;
        }

        if ($contact !== null && $contact->client_id !== $workflow->client_id) {
            return null;
        }

        $run = AutomationRun::query()->create([
            'automation_workflow_id' => $workflow->id,
            'client_id' => $workflow->client_id,
            'contact_id' => $contact?->id,
            'campaign_id' => $context['campaign_id'] ?? null,
            'trigger' => $workflow->trigger,
            'status' => AutomationRunStatus::Queued,
            'current_position' => 1,
            'context' => $context === [] ? null : $context,
        ]);

        $workflow->forceFill([
            'runs_count' => $workflow->runs_count + 1,
            'last_run_at' => now(),
        ])->save();

        RunAutomationStep::dispatch($run->id)
            ->onQueue((string) config('automations.queue', 'default'));

        return $run;
    }

    /**
     * The active workflows of the tenant that listen for the trigger.
     *
     * @return Collection<int, AutomationWorkflow>
     */
    protected function workflowsFor(AutomationTrigger $trigger, int $clientId): Collection
    {
        return AutomationWorkflow::query()
            ->forClient($clientId)
            ->active()
            ->where('trigger', $trigger->value)
            ->with('steps')
            ->orderBy('id')
            ->get();
    }

    /**
     * Whether the contact satisfies the trigger's own configuration.
     */
    protected function matches(AutomationWorkflow $workflow, Contact $contact): bool
    {
        if ($workflow->trigger !== AutomationTrigger::ContactTagged) {
            return true;
        }

        $tagId = $workflow->trigger_config['tag_id'] ?? null;

        if (! is_numeric($tagId)) {
            return true;
        }

        return $contact->tags()->whereKey((int) $tagId)->exists();
    }
}
