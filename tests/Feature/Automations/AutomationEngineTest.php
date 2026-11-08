<?php

use App\Enums\AutomationAction;
use App\Enums\AutomationRunStatus;
use App\Enums\AutomationTrigger;
use App\Jobs\RunAutomationStep;
use App\Models\AutomationRun;
use App\Models\AutomationStep;
use App\Models\AutomationWorkflow;
use App\Models\Contact;
use App\Models\Tag;
use App\Services\Automation\AutomationEngine;
use App\Services\Automation\AutomationRunner;
use Illuminate\Support\Facades\Bus;

beforeEach(function (): void {
    config()->set('automations.enabled', true);

    [$this->client, $this->wallet, $this->user] = clientWithWallet();
});

/**
 * A workflow with one step, ready for a run.
 *
 * @param  array<string, mixed>  $config
 */
function workflowWithStep($client, AutomationAction $action, array $config = [], int $position = 1): AutomationWorkflow
{
    $workflow = AutomationWorkflow::factory()->for($client)->create();

    AutomationStep::factory()
        ->action($action, $position, $config)
        ->for($workflow, 'workflow')
        ->create();

    return $workflow;
}

it('starts a queued run and queues the first slice of work', function (): void {
    Bus::fake();

    $contact = Contact::factory()->for($this->client)->create();
    $workflow = workflowWithStep($this->client, AutomationAction::Stop);

    $started = app(AutomationEngine::class)->emit(AutomationTrigger::ContactCreated, $contact);

    $run = AutomationRun::query()->sole();

    expect($started)->toBe(1)
        ->and($run->client_id)->toBe($this->client->id)
        ->and($run->contact_id)->toBe($contact->id)
        ->and($run->status)->toBe(AutomationRunStatus::Queued)
        ->and($run->current_position)->toBe(1)
        ->and($workflow->refresh()->runs_count)->toBe(1);

    Bus::assertDispatched(RunAutomationStep::class);
});

it('skips a paused workflow and a workflow with no steps', function (): void {
    Bus::fake();

    $contact = Contact::factory()->for($this->client)->create();

    AutomationWorkflow::factory()->for($this->client)->inactive()->create();
    AutomationWorkflow::factory()->for($this->client)->create();

    $started = app(AutomationEngine::class)->emit(AutomationTrigger::ContactCreated, $contact);

    expect($started)->toBe(0)
        ->and(AutomationRun::query()->count())->toBe(0);

    Bus::assertNotDispatched(RunAutomationStep::class);
});

it('only enrols a contact that carries the trigger tag', function (): void {
    Bus::fake();

    $tag = Tag::factory()->for($this->client)->create(['name' => 'VIP']);

    $workflow = AutomationWorkflow::factory()->for($this->client)
        ->triggeredBy(AutomationTrigger::ContactTagged, ['tag_id' => $tag->id])
        ->create();

    AutomationStep::factory()
        ->action(AutomationAction::Stop)
        ->for($workflow, 'workflow')
        ->create();

    $tagged = Contact::factory()->for($this->client)->create();
    $tagged->tags()->attach($tag->id);

    $plain = Contact::factory()->for($this->client)->create();

    $engine = app(AutomationEngine::class);

    expect($engine->emit(AutomationTrigger::ContactTagged, $tagged))->toBe(1)
        ->and($engine->emit(AutomationTrigger::ContactTagged, $plain))->toBe(0)
        ->and(AutomationRun::query()->count())->toBe(1);
});

it('works a run through its steps and completes it', function (): void {
    $tag = Tag::factory()->for($this->client)->create(['name' => 'Greeted']);
    $contact = Contact::factory()->for($this->client)->create();
    $workflow = workflowWithStep($this->client, AutomationAction::AddTag, ['tag_id' => $tag->id]);

    $run = AutomationRun::factory()->for($workflow, 'workflow')->create([
        'client_id' => $this->client->id,
        'contact_id' => $contact->id,
    ]);

    app(AutomationRunner::class)->advance($run);

    $run->refresh();

    expect($contact->refresh()->tags()->whereKey($tag->id)->exists())->toBeTrue()
        ->and($run->status)->toBe(AutomationRunStatus::Completed)
        ->and($run->finished_at)->not->toBeNull()
        ->and($run->stepLogs()->count())->toBe(1);
});

it('pauses a run on a wait step and queues the resume', function (): void {
    Bus::fake();

    $contact = Contact::factory()->for($this->client)->create();
    $workflow = workflowWithStep($this->client, AutomationAction::Wait, ['minutes' => 30]);

    $run = AutomationRun::factory()->for($workflow, 'workflow')->create([
        'client_id' => $this->client->id,
        'contact_id' => $contact->id,
    ]);

    app(AutomationRunner::class)->advance($run);

    $run->refresh();

    expect($run->status)->toBe(AutomationRunStatus::Waiting)
        ->and($run->current_position)->toBe(2)
        ->and($run->resume_at)->not->toBeNull()
        ->and($run->resume_at->isFuture())->toBeTrue();

    Bus::assertDispatched(RunAutomationStep::class);
});

it('jumps to the failing branch of a condition', function (): void {
    $tag = Tag::factory()->for($this->client)->create(['name' => 'VIP']);
    $contact = Contact::factory()->for($this->client)->create();
    $workflow = AutomationWorkflow::factory()->for($this->client)->create();

    AutomationStep::factory()
        ->action(AutomationAction::Condition, 1, [
            'check' => 'has_tag',
            'tag_id' => $tag->id,
            'on_false_position' => 3,
        ])
        ->for($workflow, 'workflow')
        ->create();

    AutomationStep::factory()
        ->action(AutomationAction::AddTag, 2, ['tag_id' => $tag->id])
        ->for($workflow, 'workflow')
        ->create();

    AutomationStep::factory()
        ->action(AutomationAction::Stop, 3)
        ->for($workflow, 'workflow')
        ->create();

    $run = AutomationRun::factory()->for($workflow, 'workflow')->create([
        'client_id' => $this->client->id,
        'contact_id' => $contact->id,
    ]);

    app(AutomationRunner::class)->advance($run);

    $run->refresh();

    expect($contact->refresh()->tags()->count())->toBe(0)
        ->and($run->status)->toBe(AutomationRunStatus::Completed)
        ->and($run->stepLogs()->pluck('position')->all())->toBe([1, 3]);
});

it('cancels a run whose workflow was switched off', function (): void {
    $contact = Contact::factory()->for($this->client)->create();
    $workflow = workflowWithStep($this->client, AutomationAction::Stop);

    $run = AutomationRun::factory()->for($workflow, 'workflow')->create([
        'client_id' => $this->client->id,
        'contact_id' => $contact->id,
    ]);

    $workflow->forceFill(['is_active' => false])->save();

    app(AutomationRunner::class)->advance($run);

    expect($run->refresh()->status)->toBe(AutomationRunStatus::Cancelled);
});
