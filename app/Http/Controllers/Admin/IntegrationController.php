<?php

namespace App\Http\Controllers\Admin;

use App\Concerns\RecordsActivity;
use App\Enums\Integration;
use App\Http\Controllers\Controller;
use App\Services\IntegrationScripts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The tracking codes the platform team pastes in from third-party vendors.
 *
 * A snippet is stored even while its integration is switched off, so a tag can
 * be parked without losing the code. Enabled snippets are printed on every
 * page by the root Blade view.
 */
class IntegrationController extends Controller
{
    use RecordsActivity;

    /**
     * Every supported integration with the code currently stored for it.
     */
    public function index(IntegrationScripts $scripts): Response
    {
        Gate::authorize('users.manage');

        $state = $scripts->state();

        return Inertia::render('admin/integrations/index', [
            'integrations' => array_map(fn (Integration $integration): array => [
                'value' => $integration->value,
                'label' => $integration->label(),
                'description' => $integration->description(),
                'docs_url' => $integration->docsUrl(),
                'sample_head' => $integration->sampleHead(),
                'sample_body' => $integration->sampleBody(),
                'head' => $state[$integration->value]['head'],
                'body' => $state[$integration->value]['body'],
                'enabled' => $state[$integration->value]['enabled'],
            ], Integration::cases()),
        ]);
    }

    /**
     * Store the snippets in one request, blanks included.
     */
    public function update(Request $request, IntegrationScripts $scripts): RedirectResponse
    {
        Gate::authorize('users.manage');

        $validated = $request->validate([
            'integrations' => ['required', 'array', 'min:1'],
            'integrations.*.value' => ['required', 'string', Rule::enum(Integration::class)],
            'integrations.*.enabled' => ['required', 'boolean'],
            'integrations.*.head' => ['nullable', 'string', 'max:20000'],
            'integrations.*.body' => ['nullable', 'string', 'max:20000'],
        ]);

        $changed = [];

        foreach ($validated['integrations'] as $attributes) {
            $integration = Integration::from($attributes['value']);
            $enabled = (bool) $attributes['enabled'];

            $scripts->save($integration, $attributes['head'] ?? null, $attributes['body'] ?? null, $enabled, $request->user()->id);

            $changed[$integration->value] = $enabled;
        }

        $this->recordActivity('updated integration scripts', properties: ['integrations' => $changed]);

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Integration scripts saved.',
        ]);
    }
}
