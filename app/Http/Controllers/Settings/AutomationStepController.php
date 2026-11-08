<?php

namespace App\Http\Controllers\Settings;

use App\Enums\AutomationAction;
use App\Http\Controllers\Concerns\AuthorizesAutomations;
use App\Http\Controllers\Controller;
use App\Models\AutomationStep;
use App\Models\AutomationWorkflow;
use App\Models\Client;
use App\Models\ContactList;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The ordered steps a workflow runs through.
 *
 * A step is stored with the configuration its action needs, validated here so
 * the runner never has to guess: a tag step always names one of the tenant's
 * tags and a webhook step always carries a signing secret. Reordering rewrites
 * the positions, which is why a run already in flight may be pointed at the new
 * order — the engine re-reads the step at every position it reaches.
 */
class AutomationStepController extends Controller
{
    use AuthorizesAutomations;

    /**
     * Add a step to the end of the workflow.
     */
    public function store(Request $request, AutomationWorkflow $workflow): RedirectResponse
    {
        $this->authorizeManage($request);

        $client = $this->client($request);
        $this->authorizeWorkflow($workflow, $client);

        $limit = max(1, (int) config('automations.max_steps_per_workflow', 15));

        if ($workflow->steps()->count() >= $limit) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => "A workflow holds at most {$limit} steps.",
            ]);
        }

        $data = $this->validated($request, $client, $workflow, null);

        $position = (int) $workflow->steps()->max('position') + 1;

        $workflow->steps()->create([
            'position' => $position,
            'action' => $data['action'],
            'label' => $this->label($data),
            'config' => $this->config($data, null),
        ]);

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Step {$position} was added.",
        ]);
    }

    /**
     * Change what a step does.
     */
    public function update(Request $request, AutomationWorkflow $workflow, AutomationStep $step): RedirectResponse
    {
        $this->authorizeManage($request);

        $client = $this->client($request);
        $this->authorizeWorkflow($workflow, $client);
        $this->authorizeStep($workflow, $step);

        $data = $this->validated($request, $client, $workflow, $step);

        $step->update([
            'action' => $data['action'],
            'label' => $this->label($data),
            'config' => $this->config($data, $step),
        ]);

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Step {$step->position} was updated.",
        ]);
    }

    /**
     * Remove a step and close the gap it leaves.
     */
    public function destroy(Request $request, AutomationWorkflow $workflow, AutomationStep $step): RedirectResponse
    {
        $this->authorizeManage($request);

        $this->authorizeWorkflow($workflow, $this->client($request));
        $this->authorizeStep($workflow, $step);

        $position = $step->position;

        $step->delete();

        $workflow->steps()
            ->where('position', '>', $position)
            ->orderBy('position')
            ->get()
            ->each(fn (AutomationStep $remaining) => $remaining
                ->forceFill(['position' => $remaining->position - 1])
                ->save());

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Step {$position} was removed.",
        ]);
    }

    /**
     * Move a step one place up or down.
     */
    public function move(Request $request, AutomationWorkflow $workflow, AutomationStep $step): RedirectResponse
    {
        $this->authorizeManage($request);

        $this->authorizeWorkflow($workflow, $this->client($request));
        $this->authorizeStep($workflow, $step);

        $up = $request->string('direction')->toString() === 'up';
        $position = $step->position;
        $targetPosition = $up ? $position - 1 : $position + 1;

        $target = $workflow->steps()->where('position', $targetPosition)->first();

        if ($target === null) {
            return back();
        }

        DB::transaction(function () use ($step, $target, $position, $targetPosition): void {
            $step->forceFill(['position' => 0])->save();
            $target->forceFill(['position' => $position])->save();
            $step->forceFill(['position' => $targetPosition])->save();
        });

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'The step order was updated.',
        ]);
    }

    /**
     * Refuse a step that belongs to another workflow.
     */
    protected function authorizeStep(AutomationWorkflow $workflow, AutomationStep $step): void
    {
        abort_unless((int) $step->automation_workflow_id === (int) $workflow->id, 404);
    }

    /**
     * The label the operator chose, or null so the action name shows.
     *
     * @param  array<string, mixed>  $data
     */
    protected function label(array $data): ?string
    {
        $label = trim((string) ($data['label'] ?? ''));

        return $label === '' ? null : $label;
    }

    /**
     * Validate a step, then insist on the fields its action cannot work without.
     *
     * @return array<string, mixed>
     */
    protected function validated(
        Request $request,
        Client $client,
        AutomationWorkflow $workflow,
        ?AutomationStep $step,
    ): array {
        $maxWait = max(1, (int) config('automations.max_wait_minutes', 43200));

        $data = $request->validate([
            'action' => ['required', 'string', Rule::in(AutomationAction::values())],
            'label' => ['nullable', 'string', 'max:80'],
            'body' => ['nullable', 'string', 'max:2000'],
            'subject' => ['nullable', 'string', 'max:160'],
            'from' => ['nullable', 'string', 'max:32'],
            'tag_id' => ['nullable', 'integer'],
            'contact_list_id' => ['nullable', 'integer'],
            'check' => ['nullable', 'string', Rule::in($this->checks())],
            'field' => ['nullable', 'string', 'max:60'],
            'value' => ['nullable', 'string', 'max:120'],
            'minutes' => ['nullable', 'integer', 'min:1', 'max:'.$maxWait],
            'url' => ['nullable', 'string', 'max:255'],
            'secret' => ['nullable', 'string', 'max:120'],
            'on_false_position' => ['nullable', 'integer', 'min:1'],
        ]);

        $action = AutomationAction::from((string) $data['action']);

        if (in_array($action, [AutomationAction::SendSms, AutomationAction::SendEmail], true)
            && trim((string) ($data['body'] ?? '')) === '') {
            throw ValidationException::withMessages(['body' => 'Write the message the step sends.']);
        }

        if (in_array($action, [AutomationAction::AddTag, AutomationAction::RemoveTag], true)
            || ($action === AutomationAction::Condition && ($data['check'] ?? null) === 'has_tag')) {
            $this->assertOwnedTag($client, (int) ($data['tag_id'] ?? 0));
        }

        if (in_array($action, [AutomationAction::AddToList, AutomationAction::RemoveFromList], true)
            || ($action === AutomationAction::Condition && ($data['check'] ?? null) === 'in_list')) {
            $this->assertOwnedList($client, (int) ($data['contact_list_id'] ?? 0));
        }

        if ($action === AutomationAction::Condition) {
            if (trim((string) ($data['check'] ?? '')) === '') {
                throw ValidationException::withMessages(['check' => 'Choose the check this step makes.']);
            }

            if (($data['check'] ?? null) === 'custom_field' && trim((string) ($data['field'] ?? '')) === '') {
                throw ValidationException::withMessages(['field' => 'Name the custom field to compare.']);
            }

            $target = $data['on_false_position'] ?? null;

            if ($target !== null && $step !== null && (int) $target === $step->position) {
                throw ValidationException::withMessages([
                    'on_false_position' => 'The step cannot jump to itself.',
                ]);
            }
        }

        if ($action === AutomationAction::Wait && ($data['minutes'] ?? null) === null) {
            throw ValidationException::withMessages(['minutes' => 'Choose how long the run waits.']);
        }

        if ($action === AutomationAction::Webhook) {
            $url = trim((string) ($data['url'] ?? ''));

            if ($url === '') {
                throw ValidationException::withMessages(['url' => 'Enter the URL the step posts to.']);
            }

            if (! str_starts_with($url, 'https://')) {
                throw ValidationException::withMessages([
                    'url' => 'The receiver must be an https URL; the payload is signed but not encrypted.',
                ]);
            }
        }

        return $data;
    }

    /**
     * Insist the tag exists for this tenant.
     */
    protected function assertOwnedTag(Client $client, int $tagId): void
    {
        $owned = $tagId > 0 && Tag::query()->where('client_id', $client->id)->whereKey($tagId)->exists();

        if (! $owned) {
            throw ValidationException::withMessages([
                'tag_id' => 'Choose one of this client\'s tags.',
            ]);
        }
    }

    /**
     * Insist the contact list exists for this tenant.
     */
    protected function assertOwnedList(Client $client, int $listId): void
    {
        $owned = $listId > 0 && ContactList::query()->where('client_id', $client->id)->whereKey($listId)->exists();

        if (! $owned) {
            throw ValidationException::withMessages([
                'contact_list_id' => 'Choose one of this client\'s contact lists.',
            ]);
        }
    }

    /**
     * The checks a condition step understands, mirroring the runner.
     *
     * @return list<string>
     */
    protected function checks(): array
    {
        return ['has_tag', 'in_list', 'dnd', 'not_dnd', 'has_email', 'has_phone', 'custom_field'];
    }

    /**
     * The configuration stored on the step, holding only what its action reads.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function config(array $data, ?AutomationStep $step): array
    {
        $action = AutomationAction::from((string) $data['action']);

        return match ($action) {
            AutomationAction::SendSms => $this->compact([
                'body' => (string) $data['body'],
                'from' => $data['from'] ?? null,
            ]),
            AutomationAction::SendEmail => $this->compact([
                'subject' => $data['subject'] ?? null,
                'body' => (string) $data['body'],
                'from' => $data['from'] ?? null,
            ]),
            AutomationAction::AddTag, AutomationAction::RemoveTag => [
                'tag_id' => (int) $data['tag_id'],
            ],
            AutomationAction::AddToList, AutomationAction::RemoveFromList => [
                'contact_list_id' => (int) $data['contact_list_id'],
            ],
            AutomationAction::Condition => $this->compact([
                'check' => (string) $data['check'],
                'tag_id' => isset($data['tag_id']) ? (int) $data['tag_id'] : null,
                'contact_list_id' => isset($data['contact_list_id']) ? (int) $data['contact_list_id'] : null,
                'field' => $data['field'] ?? null,
                'value' => $data['value'] ?? null,
                'on_false_position' => isset($data['on_false_position']) ? (int) $data['on_false_position'] : null,
            ]),
            AutomationAction::Wait => ['minutes' => (int) $data['minutes']],
            AutomationAction::Webhook => [
                'url' => (string) $data['url'],
                'secret' => $this->secret($data, $step),
            ],
            AutomationAction::SetDnd, AutomationAction::LiftDnd, AutomationAction::Stop => [],
        };
    }

    /**
     * Drop the fields the operator left empty.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected function compact(array $values): array
    {
        return array_filter(
            $values,
            static fn (mixed $value): bool => $value !== null && (! is_string($value) || trim($value) !== ''),
        );
    }

    /**
     * The webhook signing secret: the one supplied, the one already stored, or a
     * freshly issued one so a saved webhook step is always verifiable.
     *
     * @param  array<string, mixed>  $data
     */
    protected function secret(array $data, ?AutomationStep $step): string
    {
        $supplied = trim((string) ($data['secret'] ?? ''));

        if ($supplied !== '') {
            return $supplied;
        }

        $stored = trim((string) ($step?->value('secret') ?? ''));

        if ($stored !== '') {
            return $stored;
        }

        return (string) config('automations.webhook.secret_prefix', 'autsec_').Str::random(32);
    }
}
