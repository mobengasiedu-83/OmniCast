<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\Permission;
use App\Models\AutomationWorkflow;
use App\Models\Client;
use Illuminate\Http\Request;

/**
 * The gate shared by the automation screens.
 *
 * Every automation screen acts for one tenant, so the helpers resolve the client
 * the request is scoped to, refuse a viewer without the automation permission
 * and hide another tenant's workflow behind a 404.
 */
trait AuthorizesAutomations
{
    use ResolvesTenantClient;

    /**
     * Refuse a viewer without the automation permission.
     */
    protected function authorizeView(Request $request): void
    {
        abort_unless($request->user()?->can(Permission::ViewAutomations->value) === true, 403);
    }

    /**
     * Refuse a viewer who may not change workflows.
     */
    protected function authorizeManage(Request $request): void
    {
        abort_unless($request->user()?->can(Permission::ManageAutomations->value) === true, 403);
    }

    /**
     * The tenant this request acts for.
     */
    protected function client(Request $request): Client
    {
        $client = $this->tenantClient($request);

        abort_if($client === null, 403, 'Select a client account before managing automations.');

        return $client;
    }

    /**
     * Stop a workflow of another tenant from being read or changed.
     */
    protected function authorizeWorkflow(AutomationWorkflow $workflow, Client $client): void
    {
        abort_unless((int) $workflow->client_id === (int) $client->id, 404);
    }
}
