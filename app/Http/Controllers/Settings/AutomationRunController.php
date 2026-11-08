<?php

namespace App\Http\Controllers\Settings;

use App\Enums\AutomationRunStatus;
use App\Http\Controllers\Concerns\AuthorizesAutomations;
use App\Http\Controllers\Controller;
use App\Models\AutomationRun;
use App\Models\AutomationRunStep;
use App\Models\AutomationWorkflow;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * What the tenant's workflows actually did.
 *
 * A run is the record of one contact (or one campaign) moving through a
 * workflow: the screen lists the newest runs with an optional workflow and
 * status filter, and the detail screen shows the timeline the engine logged, so
 * an operator can see which step was skipped and why.
 */
class AutomationRunController extends Controller
{
    use AuthorizesAutomations;

    /**
     * List the tenant's runs.
     */
    public function index(Request $request): Response
    {
        $this->authorizeView($request);

        $client = $this->client($request);

        $filters = $request->validate([
            'workflow' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', Rule::enum(AutomationRunStatus::class)],
        ]);

        $status = $filters['status'] ?? null;
        $workflowId = isset($filters['workflow']) ? (int) $filters['workflow'] : null;

        $runs = AutomationRun::query()
            ->where('client_id', $client->id)
            ->when($workflowId !== null, fn ($query) => $query->where('automation_workflow_id', $workflowId))
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->with(['workflow:id,name', 'contact:id,first_name,last_name,phone'])
            ->withCount('stepLogs')
            ->latest('id')
            ->limit(50)
            ->get();

        return Inertia::render('automations/runs', [
            'runs' => $runs
                ->map(fn (AutomationRun $run): array => $this->payload($run))
                ->values()
                ->all(),
            'filters' => [
                'workflow' => $workflowId,
                'status' => $status,
            ],
            'workflows' => AutomationWorkflow::query()
                ->where('client_id', $client->id)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (AutomationWorkflow $workflow): array => ['id' => $workflow->id, 'name' => $workflow->name])
                ->values()
                ->all(),
            'statuses' => array_map(fn (AutomationRunStatus $case): array => [
                'value' => $case->value,
                'label' => $case->label(),
            ], AutomationRunStatus::cases()),
            'enabled' => (bool) config('automations.enabled'),
        ]);
    }

    /**
     * Show one run with the timeline of the steps it worked through.
     */
    public function show(Request $request, AutomationRun $run): Response
    {
        $this->authorizeView($request);

        $client = $this->client($request);

        abort_unless((int) $run->client_id === (int) $client->id, 404);

        $run->load([
            'workflow:id,name,description,is_active',
            'contact:id,first_name,last_name,phone,email',
            'campaign:id,name',
            'stepLogs',
        ]);

        $payload = $this->payload($run);
        $payload['steps'] = $run->stepLogs
            ->map(fn (AutomationRunStep $log): array => [
                'id' => $log->id,
                'position' => $log->position,
                'action' => $log->action->value,
                'action_label' => $log->action->label(),
                'status' => $log->status->value,
                'status_label' => $log->status->label(),
                'detail' => $log->detail,
                'ran_at' => $log->ran_at?->toIso8601String(),
            ])
            ->values()
            ->all();
        $payload['workflow'] = [
            'id' => $run->workflow?->id,
            'name' => $run->workflow?->name,
            'is_active' => (bool) $run->workflow?->is_active,
        ];
        $payload['contact'] = $run->contact === null
            ? null
            : [
                'id' => $run->contact->id,
                'name' => $run->contact->full_name,
                'phone' => $run->contact->phone,
                'email' => $run->contact->email,
            ];
        $payload['campaign'] = $run->campaign === null
            ? null
            : ['id' => $run->campaign->id, 'name' => $run->campaign->name];

        return Inertia::render('automations/run', [
            'run' => $payload,
            'enabled' => (bool) config('automations.enabled'),
        ]);
    }

    /**
     * The screen shape of a run.
     *
     * @return array<string, mixed>
     */
    protected function payload(AutomationRun $run): array
    {
        return [
            'id' => $run->id,
            'status' => $run->status->value,
            'status_label' => $run->status->label(),
            'trigger' => $run->trigger->value,
            'trigger_label' => $run->trigger->label(),
            'workflow' => [
                'id' => $run->workflow?->id,
                'name' => $run->workflow?->name,
            ],
            'contact_name' => $run->contact?->full_name,
            'current_position' => $run->current_position,
            'detail' => $run->detail,
            'step_logs_count' => (int) ($run->step_logs_count ?? $run->stepLogs->count()),
            'resume_at' => $run->resume_at?->toIso8601String(),
            'started_at' => $run->started_at?->toIso8601String(),
            'finished_at' => $run->finished_at?->toIso8601String(),
            'created_at' => $run->created_at?->toIso8601String(),
        ];
    }
}
