<?php

namespace App\Http\Controllers\Settings;

use App\Enums\AutomationAction;
use App\Enums\AutomationTrigger;
use App\Http\Controllers\Concerns\AuthorizesAutomations;
use App\Http\Controllers\Controller;
use App\Models\AutomationRun;
use App\Models\AutomationStep;
use App\Models\AutomationWorkflow;
use App\Models\Client;
use App\Models\ContactList;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The tenant's workflow automations.
 *
 * A workflow is one trigger plus an ordered list of steps. This screen only owns
 * the definition: a run is written and worked through by the automation engine,
 * so saving a workflow never sends anything on its own. Steps are managed by
 * {@see AutomationStepController} and history is read through
 * {@see AutomationRunController}.
 */
class AutomationWorkflowController extends Controller
{
    use AuthorizesAutomations;

    /**
     * List the tenant's workflows with a step summary.
     */
    public function index(Request $request): Response
    {
        $this->authorizeView($request);

        $client = $this->client($request);
        $tags = $this->tags($client);
        $lists = $this->lists($client);

        $workflows = AutomationWorkflow::query()
            ->forClient($client->id)
            ->with('steps')
            ->withCount(['runs as open_runs_count' => fn (Builder $query) => $query->open()])
            ->orderBy('name')
            ->get();

        return Inertia::render('automations/index', [
            'workflows' => $workflows
                ->map(fn (AutomationWorkflow $workflow): array => $this->payload($workflow, $tags, $lists))
                ->values()
                ->all(),
            'recentRuns' => AutomationRun::query()
                ->where('client_id', $client->id)
                ->with(['workflow:id,name', 'contact:id,first_name,last_name,phone'])
                ->latest('id')
                ->limit(10)
                ->get()
                ->map(fn (AutomationRun $run): array => $this->runSummary($run))
                ->values()
                ->all(),
            'triggers' => $this->triggerCatalogue(),
            'tags' => $this->tagOptions($tags),
            'limits' => $this->limits(),
            'enabled' => (bool) config('automations.enabled'),
        ]);
    }

    /**
     * Create a workflow.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManage($request);

        $client = $this->client($request);
        $data = $this->validated($request, $client);

        $workflow = AutomationWorkflow::query()->create([
            'client_id' => $client->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'trigger' => $data['trigger'],
            'trigger_config' => $this->triggerConfig($data),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'created_by' => $request->user()?->id,
        ]);

        return to_route('automations.show', $workflow)->with('toast', [
            'type' => 'success',
            'message' => 'The workflow was created. Add the steps it should run.',
        ]);
    }

    /**
     * Show one workflow with its steps and recent runs.
     */
    public function show(Request $request, AutomationWorkflow $workflow): Response
    {
        $this->authorizeView($request);

        $client = $this->client($request);
        $this->authorizeWorkflow($workflow, $client);

        $tags = $this->tags($client);
        $lists = $this->lists($client);

        $runs = $workflow->runs()
            ->with(['contact:id,first_name,last_name,phone'])
            ->latest('id')
            ->limit(25)
            ->get();

        return Inertia::render('automations/show', [
            'workflow' => $this->payload($workflow->load('steps'), $tags, $lists),
            'runs' => $runs->map(fn (AutomationRun $run): array => $this->runSummary($run))->values()->all(),
            'triggers' => $this->triggerCatalogue(),
            'actions' => $this->actionCatalogue(),
            'conditions' => $this->conditionCatalogue(),
            'tags' => $this->tagOptions($tags),
            'contactLists' => $this->listOptions($lists),
            'limits' => $this->limits(),
            'enabled' => (bool) config('automations.enabled'),
        ]);
    }

    /**
     * Change a workflow's definition.
     */
    public function update(Request $request, AutomationWorkflow $workflow): RedirectResponse
    {
        $this->authorizeManage($request);

        $client = $this->client($request);
        $this->authorizeWorkflow($workflow, $client);

        $data = $this->validated($request, $client);

        $workflow->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'trigger' => $data['trigger'],
            'trigger_config' => $this->triggerConfig($data),
            'is_active' => (bool) ($data['is_active'] ?? $workflow->is_active),
        ]);

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'The workflow was updated.',
        ]);
    }

    /**
     * Switch a workflow on or off.
     */
    public function toggle(Request $request, AutomationWorkflow $workflow): RedirectResponse
    {
        $this->authorizeManage($request);

        $this->authorizeWorkflow($workflow, $this->client($request));

        $isActive = ! $workflow->is_active;

        $workflow->forceFill(['is_active' => $isActive])->save();

        return back()->with('toast', [
            'type' => 'success',
            'message' => $isActive
                ? 'The workflow is live and will run when its trigger fires.'
                : 'The workflow is paused; runs already started keep going.',
        ]);
    }

    /**
     * Delete a workflow with its steps and history.
     */
    public function destroy(Request $request, AutomationWorkflow $workflow): RedirectResponse
    {
        $this->authorizeManage($request);

        $this->authorizeWorkflow($workflow, $this->client($request));

        $workflow->delete();

        return to_route('automations.index')->with('toast', [
            'type' => 'success',
            'message' => 'The workflow was deleted.',
        ]);
    }

    /**
     * Validate the workflow definition and the field its trigger needs.
     *
     * @return array<string, mixed>
     */
    protected function validated(Request $request, Client $client): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:255'],
            'trigger' => ['required', 'string', Rule::in(AutomationTrigger::values())],
            'tag_id' => ['nullable', 'integer'],
            'hour' => ['nullable', 'integer', 'min:0', 'max:23'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $trigger = AutomationTrigger::from((string) $data['trigger']);
        $data['trigger'] = $trigger->value;

        if ($trigger === AutomationTrigger::ContactTagged) {
            $tagId = (int) ($data['tag_id'] ?? 0);

            $owned = $tagId > 0 && Tag::query()
                ->where('client_id', $client->id)
                ->whereKey($tagId)
                ->exists();

            if (! $owned) {
                throw ValidationException::withMessages([
                    'tag_id' => 'Choose one of this client\'s tags to trigger the workflow.',
                ]);
            }
        }

        if ($trigger === AutomationTrigger::Schedule && blank($data['hour'] ?? null)) {
            throw ValidationException::withMessages([
                'hour' => 'Choose the hour of the day the workflow should run.',
            ]);
        }

        return $data;
    }

    /**
     * The trigger configuration stored on the workflow.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    protected function triggerConfig(array $data): ?array
    {
        return match (AutomationTrigger::from((string) $data['trigger'])) {
            AutomationTrigger::ContactTagged => ['tag_id' => (int) $data['tag_id']],
            AutomationTrigger::Schedule => ['hour' => (int) ($data['hour'] ?? 9)],
            default => null,
        };
    }

    /**
     * The tenant's tags, so the screen can name what a step points at.
     *
     * @return Collection<int, Tag>
     */
    protected function tags(Client $client): Collection
    {
        return Tag::query()->where('client_id', $client->id)->orderBy('name')->get(['id', 'name']);
    }

    /**
     * @return Collection<int, ContactList>
     */
    protected function lists(Client $client): Collection
    {
        return ContactList::query()->where('client_id', $client->id)->orderBy('name')->get(['id', 'name']);
    }

    /**
     * @param  Collection<int, Tag>  $tags
     * @return list<array{id: int, name: string}>
     */
    protected function tagOptions(Collection $tags): array
    {
        return $tags
            ->map(fn (Tag $tag): array => ['id' => $tag->id, 'name' => $tag->name])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, ContactList>  $lists
     * @return list<array{id: int, name: string}>
     */
    protected function listOptions(Collection $lists): array
    {
        return $lists
            ->map(fn (ContactList $list): array => ['id' => $list->id, 'name' => $list->name])
            ->values()
            ->all();
    }

    /**
     * The engine limits the builder screen shows.
     *
     * @return array{max_steps: int, max_wait_minutes: int}
     */
    protected function limits(): array
    {
        return [
            'max_steps' => (int) config('automations.max_steps_per_workflow', 15),
            'max_wait_minutes' => (int) config('automations.max_wait_minutes', 43200),
        ];
    }

    /**
     * The screen shape of one workflow.
     *
     * @param  Collection<int, Tag>  $tags
     * @param  Collection<int, ContactList>  $lists
     * @return array<string, mixed>
     */
    protected function payload(AutomationWorkflow $workflow, Collection $tags, Collection $lists): array
    {
        $workflow->loadMissing('steps');

        $tagNames = $tags->pluck('name', 'id');
        $listNames = $lists->pluck('name', 'id');

        return [
            'id' => $workflow->id,
            'name' => $workflow->name,
            'description' => $workflow->description,
            'trigger' => $workflow->trigger->value,
            'trigger_label' => $workflow->trigger->label(),
            'trigger_summary' => $this->triggerSummary($workflow, $tagNames),
            'trigger_config' => $workflow->trigger_config,
            'is_active' => $workflow->is_active,
            'runs_count' => $workflow->runs_count,
            'open_runs_count' => (int) ($workflow->open_runs_count ?? 0),
            'last_run_at' => $workflow->last_run_at?->toIso8601String(),
            'created_at' => $workflow->created_at?->toIso8601String(),
            'steps' => $workflow->steps
                ->map(fn (AutomationStep $step): array => $this->stepPayload($step, $tagNames, $listNames))
                ->values()
                ->all(),
        ];
    }

    /**
     * The screen shape of one step.
     *
     * @param  Collection<int, string>  $tagNames
     * @param  Collection<int, string>  $listNames
     * @return array<string, mixed>
     */
    protected function stepPayload(AutomationStep $step, Collection $tagNames, Collection $listNames): array
    {
        return [
            'id' => $step->id,
            'position' => $step->position,
            'action' => $step->action->value,
            'action_label' => $step->action->label(),
            'label' => $step->label,
            'describe' => $step->describe(),
            'summary' => $this->stepSummary($step, $tagNames, $listNames),
            'config' => $step->config ?? [],
        ];
    }

    /**
     * One line telling the operator what the step does with its configuration.
     *
     * @param  Collection<int, string>  $tagNames
     * @param  Collection<int, string>  $listNames
     */
    protected function stepSummary(AutomationStep $step, Collection $tagNames, Collection $listNames): string
    {
        $tagName = $tagNames[$step->integer('tag_id') ?? 0] ?? 'a missing tag';
        $listName = $listNames[$step->integer('contact_list_id') ?? 0] ?? 'a missing list';
        $fallback = $step->integer('on_false_position');

        return match ($step->action) {
            AutomationAction::SendSms => 'Texts: '.Str::limit((string) ($step->value('body') ?? 'no message yet'), 70),
            AutomationAction::SendEmail => 'Emails: '.Str::limit((string) ($step->value('subject') ?? 'no subject yet'), 70),
            AutomationAction::AddTag => 'Adds the tag '.$tagName,
            AutomationAction::RemoveTag => 'Removes the tag '.$tagName,
            AutomationAction::AddToList => 'Adds the contact to '.$listName,
            AutomationAction::RemoveFromList => 'Takes the contact off '.$listName,
            AutomationAction::Condition => 'Continues only when '.($step->value('check') ?? 'has_tag')
                .($fallback === null ? ', otherwise ends the workflow' : ', otherwise jumps to step '.$fallback),
            AutomationAction::Wait => 'Waits '.($step->integer('minutes') ?? 60).' minute(s)',
            AutomationAction::Webhook => 'Posts to '.Str::limit((string) ($step->value('url') ?? 'no URL yet'), 70),
            AutomationAction::SetDnd => 'Records do-not-disturb for the contact number',
            AutomationAction::LiftDnd => 'Lifts the do-not-disturb this tenant recorded',
            AutomationAction::Stop => 'Ends the workflow',
        };
    }

    /**
     * What makes the workflow start, in words.
     *
     * @param  Collection<int, string>  $tagNames
     */
    protected function triggerSummary(AutomationWorkflow $workflow, Collection $tagNames): string
    {
        return match ($workflow->trigger) {
            AutomationTrigger::ContactTagged => 'When a contact carries the tag '
                .($tagNames[(int) ($workflow->trigger_config['tag_id'] ?? 0)] ?? 'any tag'),
            AutomationTrigger::Schedule => 'Every day at '
                .str_pad((string) (int) ($workflow->trigger_config['hour'] ?? 9), 2, '0', STR_PAD_LEFT).':00',
            default => $workflow->trigger->description(),
        };
    }

    /**
     * The screen shape of one run, without its step timeline.
     *
     * @return array<string, mixed>
     */
    protected function runSummary(AutomationRun $run): array
    {
        return [
            'id' => $run->id,
            'status' => $run->status->value,
            'status_label' => $run->status->label(),
            'trigger' => $run->trigger->value,
            'workflow' => [
                'id' => $run->workflow?->id,
                'name' => $run->workflow?->name,
            ],
            'contact' => $run->contact === null
                ? null
                : [
                    'id' => $run->contact->id,
                    'name' => $run->contact->full_name,
                    'phone' => $run->contact->phone,
                ],
            'current_position' => $run->current_position,
            'detail' => $run->detail,
            'resume_at' => $run->resume_at?->toIso8601String(),
            'started_at' => $run->started_at?->toIso8601String(),
            'finished_at' => $run->finished_at?->toIso8601String(),
            'created_at' => $run->created_at?->toIso8601String(),
        ];
    }

    /**
     * The triggers a workflow can start on.
     *
     * @return list<array{value: string, label: string, description: string, needs_contact: bool, scheduled: bool}>
     */
    protected function triggerCatalogue(): array
    {
        return array_map(static fn (AutomationTrigger $trigger): array => [
            'value' => $trigger->value,
            'label' => $trigger->label(),
            'description' => $trigger->description(),
            'needs_contact' => $trigger->needsContact(),
            'scheduled' => $trigger->isScheduled(),
        ], AutomationTrigger::cases());
    }

    /**
     * The step actions the builder offers, with the inputs each one shows.
     *
     * @return list<array<string, mixed>>
     */
    protected function actionCatalogue(): array
    {
        return array_map(fn (AutomationAction $action): array => [
            'value' => $action->value,
            'label' => $action->label(),
            'description' => $action->description(),
            'channel' => $action->channel()?->value,
            'needs_contact' => $action->needsContact(),
            'pauses_run' => $action->pausesRun(),
            'fields' => $this->fieldsFor($action),
        ], AutomationAction::cases());
    }

    /**
     * The inputs the builder shows for one action.
     *
     * @return list<string>
     */
    protected function fieldsFor(AutomationAction $action): array
    {
        return match ($action) {
            AutomationAction::SendSms => ['body', 'from'],
            AutomationAction::SendEmail => ['subject', 'body', 'from'],
            AutomationAction::AddTag, AutomationAction::RemoveTag => ['tag_id'],
            AutomationAction::AddToList, AutomationAction::RemoveFromList => ['contact_list_id'],
            AutomationAction::SetDnd, AutomationAction::LiftDnd, AutomationAction::Stop => [],
            AutomationAction::Condition => ['check', 'tag_id', 'contact_list_id', 'field', 'value', 'on_false_position'],
            AutomationAction::Wait => ['minutes'],
            AutomationAction::Webhook => ['url', 'secret'],
        };
    }

    /**
     * The checks a condition step understands, mirroring the runner.
     *
     * @return list<array{value: string, label: string, needs_field: bool}>
     */
    protected function conditionCatalogue(): array
    {
        return [
            ['value' => 'has_tag', 'label' => 'The contact has a tag', 'needs_field' => false],
            ['value' => 'in_list', 'label' => 'The contact is in a list', 'needs_field' => false],
            ['value' => 'dnd', 'label' => 'The contact number is on DND', 'needs_field' => false],
            ['value' => 'not_dnd', 'label' => 'The contact number is not on DND', 'needs_field' => false],
            ['value' => 'has_email', 'label' => 'The contact has an email address', 'needs_field' => false],
            ['value' => 'has_phone', 'label' => 'The contact has a phone number', 'needs_field' => false],
            ['value' => 'custom_field', 'label' => 'A custom field matches a value', 'needs_field' => true],
        ];
    }
}
