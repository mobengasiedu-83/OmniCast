<?php

namespace App\Http\Controllers\Admin;

use App\Enums\WhatsAppApprovalStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\WhatsApp\StoreWhatsAppConnectorAssignmentRequest;
use App\Models\WhatsAppClientConnector;
use App\Models\WhatsAppConnector;
use App\Models\WhatsAppProvider;
use App\Services\WhatsApp\WhatsAppAuditLogger;
use App\Services\WhatsApp\WhatsAppCatalogSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class WhatsAppConnectorController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', WhatsAppClientConnector::class);

        return Inertia::render('admin/whatsapp/connectors/index', [
            'connectors' => WhatsAppConnector::with('provider:id,name')->withCount('templates')->orderBy('connector_name')->get(),
            'assignments' => WhatsAppClientConnector::with(['client:id,name', 'connector:id,connector_name,sender_number,waba_id,status'])->latest()->get(),
            'providers' => WhatsAppProvider::query()->get(['id', 'name']),
        ]);
    }

    public function sync(WhatsAppProvider $provider, WhatsAppCatalogSync $sync): RedirectResponse
    {
        Gate::authorize('test', $provider);
        $result = $sync->syncConnectors($provider);

        return back()->with('toast', ['type' => $result['failed'] ? 'error' : 'success', 'message' => $result['failed'] ? 'Connector sync failed. Check provider status and configuration.' : "Connector sync completed: {$result['created']} new, {$result['updated']} updated, {$result['unavailable']} unavailable."]);
    }

    public function syncTemplates(WhatsAppConnector $connector, WhatsAppCatalogSync $sync): RedirectResponse
    {
        Gate::authorize('viewAny', WhatsAppClientConnector::class);
        $result = $sync->syncTemplates($connector);

        return back()->with('toast', ['type' => $result['failed'] ? 'error' : 'success', 'message' => $result['failed'] ? 'Template sync failed. Check provider status.' : "Template sync completed: {$result['created']} new, {$result['updated']} updated, {$result['skipped']} skipped."]);
    }

    public function assign(StoreWhatsAppConnectorAssignmentRequest $request, WhatsAppAuditLogger $audit): RedirectResponse
    {
        $assignment = WhatsAppClientConnector::query()->create([
            'client_id' => $request->user()->client_id,
            'connector_id' => $request->integer('connector_id'),
            'enabled' => false,
            'approval_status' => WhatsAppApprovalStatus::Pending,
            'created_by' => $request->user()->id,
        ]);
        $audit->record('connector.assignment_requested', $request->user(), $assignment->client, $assignment->connector, ['assignment_id' => $assignment->id]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Sender access request submitted for review.']);
    }

    public function review(Request $request, WhatsAppClientConnector $assignment, WhatsAppAuditLogger $audit): RedirectResponse
    {
        Gate::authorize('approve', $assignment);
        $validated = $request->validate(['status' => ['required', 'in:approved,rejected']]);
        $status = WhatsAppApprovalStatus::from($validated['status']);
        $assignment->forceFill(['approval_status' => $status, 'enabled' => $status === WhatsAppApprovalStatus::Approved, 'approved_by' => $request->user()->id, 'approved_at' => now()])->save();
        $audit->record('connector.assignment_reviewed', $request->user(), $assignment->client, $assignment, ['status' => $status->value]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Sender access request reviewed.']);
    }

    public function toggle(Request $request, WhatsAppClientConnector $assignment, WhatsAppAuditLogger $audit): RedirectResponse
    {
        Gate::authorize('approve', $assignment);
        abort_unless($assignment->approval_status === WhatsAppApprovalStatus::Approved, 422);
        $assignment->forceFill(['enabled' => ! $assignment->enabled])->save();
        $audit->record('connector.assignment_toggled', $request->user(), $assignment->client, $assignment, ['enabled' => $assignment->enabled]);

        return back()->with('toast', ['type' => 'success', 'message' => $assignment->enabled ? 'Sender access enabled.' : 'Sender access disabled.']);
    }
}
