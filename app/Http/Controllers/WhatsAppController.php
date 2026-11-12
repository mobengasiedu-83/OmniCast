<?php

namespace App\Http\Controllers;

use App\Enums\WhatsAppApprovalStatus;
use App\Enums\WhatsAppConnectorStatus;
use App\Http\Requests\WhatsApp\StoreWhatsAppConnectorAssignmentRequest;
use App\Http\Requests\WhatsApp\StoreWhatsAppMediaAssetRequest;
use App\Models\WhatsAppClientConnector;
use App\Models\WhatsAppConnector;
use App\Models\WhatsAppMediaAsset;
use App\Services\WhatsApp\WhatsAppAuditLogger;
use App\Services\WhatsApp\WhatsAppMediaLibrary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class WhatsAppController extends Controller
{
    public function index(): Response
    {
        $user = request()->user();
        abort_unless($user->client_id !== null && $user->can('whatsapp.connectors.view'), 403);
        $clientId = $user->client_id;

        return Inertia::render('whatsapp/index', [
            'connectors' => WhatsAppClientConnector::query()->with('connector:id,connector_name,sender_number,waba_id,status')->where('client_id', $clientId)->get(),
            'availableConnectors' => WhatsAppConnector::query()->where('status', WhatsAppConnectorStatus::Available)->whereHas('provider', fn ($query) => $query->where('is_enabled', true))->whereDoesntHave('clientAssignments', fn ($query) => $query->where('client_id', $clientId))->get(['id', 'connector_name', 'sender_number']),
            'templates' => WhatsAppClientConnector::query()->with(['connector.templates' => fn ($query) => $query->where('status', 'approved')])->where('client_id', $clientId)->where('enabled', true)->where('approval_status', WhatsAppApprovalStatus::Approved)->get()->pluck('connector.templates')->flatten()->map(fn ($template) => ['id' => $template->id, 'name' => $template->name, 'language' => $template->language, 'category' => $template->category, 'components' => $template->components, 'versions' => $template->versions()->limit(5)->get(['version', 'status', 'captured_at'])]),
            'media' => WhatsAppMediaAsset::query()->where('client_id', $clientId)->latest()->get(['id', 'original_name', 'mime_type', 'size_bytes', 'created_at']),
            'maxUploadKilobytes' => (int) config('whatsapp.media.max_kilobytes', 16384),
        ]);
    }

    public function requestConnector(StoreWhatsAppConnectorAssignmentRequest $request, WhatsAppAuditLogger $audit): RedirectResponse
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

    public function upload(StoreWhatsAppMediaAssetRequest $request, WhatsAppMediaLibrary $library): RedirectResponse
    {
        $library->store($request->user(), $request->file('file'));

        return back()->with('toast', ['type' => 'success', 'message' => 'WhatsApp media uploaded. A temporary HTTPS link is generated when needed.']);
    }

    public function mediaUrl(WhatsAppMediaAsset $asset, WhatsAppMediaLibrary $library): JsonResponse
    {
        $user = request()->user();
        abort_unless($user->can('whatsapp.connectors.view'), 403);
        abort_unless((int) $asset->client_id === (int) $user->client_id || $user->isPlatformUser(), 404);

        return response()->json(['url' => $library->temporaryHttpsUrl($asset)]);
    }
}
