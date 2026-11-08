<?php

use App\Enums\AutomationAction;
use App\Enums\AutomationRunStatus;
use App\Enums\AutomationTrigger;
use App\Enums\Role;
use App\Models\AutomationRun;
use App\Models\AutomationStep;
use App\Models\AutomationWorkflow;
use App\Models\Tag;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    config()->set('automations.enabled', true);
    $this->withoutVite();

    [$this->client, $this->wallet, $this->user] = clientWithWallet();
});

it('renders the tenant automation screen with its own workflows', function (): void {
    $workflow = AutomationWorkflow::factory()->for($this->client)->create([
        'name' => 'Welcome journey',
        'trigger' => AutomationTrigger::ContactCreated,
    ]);

    AutomationStep::factory()
        ->action(AutomationAction::Stop, 1)
        ->for($workflow, 'workflow')
        ->create();

    $other = clientWithWallet()[0];
    AutomationWorkflow::factory()->for($other)->create(['name' => 'Other tenant journey']);

    $this->actingAs($this->user)
        ->get(route('automations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('automations/index')
            ->has('workflows', 1)
            ->where('workflows.0.name', 'Welcome journey')
            ->has('workflows.0.steps', 1)
            ->has('triggers')
            ->has('limits')
            ->where('enabled', true));
});

it('creates a workflow from the builder', function (): void {
    $tag = Tag::factory()->for($this->client)->create(['name' => 'VIP']);

    $this->actingAs($this->user)
        ->post(route('automations.store'), [
            'name' => 'Tagged VIPs',
            'description' => 'Greet the VIPs',
            'trigger' => AutomationTrigger::ContactTagged->value,
            'tag_id' => $tag->id,
        ])
        ->assertRedirect();

    $workflow = AutomationWorkflow::query()->sole();

    expect($workflow->client_id)->toBe($this->client->id)
        ->and($workflow->trigger)->toBe(AutomationTrigger::ContactTagged)
        ->and($workflow->trigger_config)->toBe(['tag_id' => $tag->id])
        ->and($workflow->is_active)->toBeTrue()
        ->and($workflow->created_by)->toBe($this->user->id);
});

it('refuses a tag trigger that belongs to another tenant', function (): void {
    $foreignTag = Tag::factory()->for(clientWithWallet()[0])->create();

    $this->actingAs($this->user)
        ->post(route('automations.store'), [
            'name' => 'Leaky trigger',
            'trigger' => AutomationTrigger::ContactTagged->value,
            'tag_id' => $foreignTag->id,
        ])
        ->assertSessionHasErrors('tag_id');

    expect(AutomationWorkflow::query()->count())->toBe(0);

    it('adds a step and stores the configuration its action needs', function (): void {
        $workflow = AutomationWorkflow::factory()->for($this->client)->create([
            'trigger' => AutomationTrigger::ContactCreated,
        ]);
        $tag = Tag::factory()->for($this->client)->create();

        $this->actingAs($this->user)
            ->post(route('automations.steps.store', ['workflow' => $workflow->id]), [
                'action' => 'add_tag',
                'tag_id' => $tag->id,
                'label' => 'Mark as greeted',
            ])
            ->assertRedirect();

        $step = $workflow->steps()->sole();

        expect($step->position)->toBe(1)
            ->and($step->action)->toBe(AutomationAction::AddTag)
            ->and($step->label)->toBe('Mark as greeted')
            ->and($step->config)->toBe(['tag_id' => $tag->id]);
    });

    it('refuses a step pointing at another tenant tag', function (): void {
        $workflow = AutomationWorkflow::factory()->for($this->client)->create();
        $foreignTag = Tag::factory()->for(clientWithWallet()[0])->create();

        $this->actingAs($this->user)
            ->post(route('automations.steps.store', ['workflow' => $workflow->id]), [
                'action' => 'add_tag',
                'tag_id' => $foreignTag->id,
            ])
            ->assertSessionHasErrors('tag_id');

        expect($workflow->steps()->count())->toBe(0);
    });

    it('issues a signing secret for a webhook step and refuses a plain http url', function (): void {
        $workflow = AutomationWorkflow::factory()->for($this->client)->create();

        $this->actingAs($this->user)
            ->post(route('automations.steps.store', ['workflow' => $workflow->id]), [
                'action' => 'webhook',
                'url' => 'http://crm.example.test/hooks',
            ])
            ->assertSessionHasErrors('url');

        $this->actingAs($this->user)
            ->post(route('automations.steps.store', ['workflow' => $workflow->id]), [
                'action' => 'webhook',
                'url' => 'https://crm.example.test/hooks',
            ])
            ->assertRedirect();

        $step = $workflow->steps()->sole();

        expect($step->config['url'])->toBe('https://crm.example.test/hooks')
            ->and($step->config['secret'])->toStartWith('autsec_');
    });

    it('moves a step up and closes the gap when one is removed', function (): void {
        $workflow = AutomationWorkflow::factory()->for($this->client)->create();

        $first = AutomationStep::factory()
            ->action(AutomationAction::Stop, 1)
            ->for($workflow, 'workflow')
            ->create();

        $second = AutomationStep::factory()
            ->action(AutomationAction::Stop, 2)
            ->for($workflow, 'workflow')
            ->create();

        $this->actingAs($this->user)
            ->patch(route('automations.steps.move', [
                'workflow' => $workflow->id,
                'step' => $second->id,
            ]), ['direction' => 'up'])
            ->assertRedirect();

        expect($second->refresh()->position)->toBe(1)
            ->and($first->refresh()->position)->toBe(2);

        $this->actingAs($this->user)
            ->delete(route('automations.steps.destroy', [
                'workflow' => $workflow->id,
                'step' => $second->id,
            ]))
            ->assertRedirect();

        expect($first->refresh()->position)->toBe(1)
            ->and($workflow->steps()->count())->toBe(1);
    });

    it('will not touch a workflow that belongs to another tenant', function (): void {
        $other = clientWithWallet()[0];
        $workflow = AutomationWorkflow::factory()->for($other)->create();

        $this->actingAs($this->user)
            ->get(route('automations.show', ['workflow' => $workflow->id]))
            ->assertNotFound();

        $this->actingAs($this->user)
            ->patch(route('automations.toggle', ['workflow' => $workflow->id]))
            ->assertNotFound();

        $this->actingAs($this->user)
            ->delete(route('automations.destroy', ['workflow' => $workflow->id]))
            ->assertNotFound();

        expect($workflow->refresh()->is_active)->toBeTrue();
    });

    it('pauses a workflow from the toggle', function (): void {
        $workflow = AutomationWorkflow::factory()->for($this->client)->create();

        $this->actingAs($this->user)
            ->patch(route('automations.toggle', ['workflow' => $workflow->id]))
            ->assertRedirect();

        expect($workflow->refresh()->is_active)->toBeFalse();
    });

    it('refuses a viewer who may not manage automations', function (): void {
        $compliance = User::factory()->forClient($this->client)->create();
        $compliance->syncRoles([Role::ComplianceOfficer->value]);

        $this->actingAs($compliance)
            ->get(route('automations.index'))
            ->assertOk();

        $this->actingAs($compliance)
            ->post(route('automations.store'), [
                'name' => 'Not allowed',
                'trigger' => AutomationTrigger::ContactCreated->value,
            ])
            ->assertForbidden();
    });

    it('lists the tenant runs and shows one timeline', function (): void {
        $workflow = AutomationWorkflow::factory()->for($this->client)->create();
        $run = AutomationRun::factory()->for($workflow, 'workflow')->create([
            'client_id' => $this->client->id,
            'status' => AutomationRunStatus::Completed,
        ]);

        $foreignWorkflow = AutomationWorkflow::factory()->for(clientWithWallet()[0])->create();
        $foreignRun = AutomationRun::factory()->for($foreignWorkflow, 'workflow')->create();

        $this->actingAs($this->user)
            ->get(route('automations.runs.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('automations/runs')
                ->has('runs', 1)
                ->where('runs.0.id', $run->id)
                ->has('statuses'));

        $this->actingAs($this->user)
            ->get(route('automations.runs.show', ['run' => $run->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('automations/run')
                ->where('run.id', $run->id)
                ->has('run.steps'));

        $this->actingAs($this->user)
            ->get(route('automations.runs.show', ['run' => $foreignRun->id]))
            ->assertNotFound();
    });

});
