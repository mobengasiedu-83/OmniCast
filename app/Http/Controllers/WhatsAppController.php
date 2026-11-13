<?php

namespace App\Http\Controllers;

use App\Enums\WhatsAppApprovalStatus;
use App\Enums\WhatsAppConnectorStatus;
use App\Http\Requests\WhatsApp\StoreWhatsAppCampaignRequest;
use App\Http\Requests\WhatsApp\StoreWhatsAppConnectorAssignmentRequest;
use App\Http\Requests\WhatsApp\StoreWhatsAppConsentRequest;
use App\Http\Requests\WhatsApp\StoreWhatsAppMediaAssetRequest;
use App\Http\Requests\WhatsApp\StoreWhatsAppTemplateSubmissionRequest;
use App\Jobs\PrepareWhatsAppCampaign;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\ContactSegment;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppCampaignRecipient;
use App\Models\WhatsAppClientConnector;
use App\Models\WhatsAppConnector;
use App\Models\WhatsAppContactConsent;
use App\Models\WhatsAppMediaAsset;
use App\Models\WhatsAppTemplateSubmission;
use App\Models\WhatsAppUsageRecord;
use App\Services\WhatsApp\WhatsAppAuditLogger;
use App\Services\WhatsApp\WhatsAppCampaignBuilder;
use App\Services\WhatsApp\WhatsAppCampaignControl;
use App\Services\WhatsApp\WhatsAppMediaLibrary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WhatsAppController extends Controller
{
    public function index(): Response
    {
        $user = request()->user();
        abort_unless($user->client_id !== null && $user->can('whatsapp.connectors.view'), 403);
        $clientId = $user->client_id;
        $wallet = Wallet::query()->where('client_id', $clientId)->first();

        return Inertia::render('whatsapp/index', [
            'connectors' => WhatsAppClientConnector::query()->with('connector:id,connector_name,sender_number,waba_id,status')->where('client_id', $clientId)->get(),
            'availableConnectors' => WhatsAppConnector::query()->where('status', WhatsAppConnectorStatus::Available)->whereHas('provider', fn ($query) => $query->where('is_enabled', true))->whereDoesntHave('clientAssignments', fn ($query) => $query->where('client_id', $clientId))->get(['id', 'connector_name', 'sender_number']),
            'metaConnections' => WhatsAppConnector::query()->where('client_id', $clientId)->whereHas('provider', fn ($query) => $query->where('driver', 'meta_cloud'))
                ->withCount('templates')->latest()->get(['id', 'connector_name', 'sender_number', 'waba_id', 'business_id', 'status', 'token_expires_at']),
            'metaSignup' => [
                'enabled' => (bool) config('whatsapp.meta.onboarding_enabled')
                    && filled(config('whatsapp.meta.app_id'))
                    && filled(config('whatsapp.meta.embedded_signup_config_id'))
                    && filled(config('whatsapp.meta.app_secret')),
                'appId' => config('whatsapp.meta.app_id'),
                'configId' => config('whatsapp.meta.embedded_signup_config_id'),
                'graphVersion' => config('whatsapp.meta.graph_version'),
                'solutionId' => config('whatsapp.meta.solution_id'),
            ],
            'templates' => WhatsAppClientConnector::query()->with(['connector.templates' => fn ($query) => $query->where('status', 'approved')->where(fn ($templates) => $templates
                ->whereNotExists(fn ($submissions) => $submissions->selectRaw('1')->from('whatsapp_template_submissions as pending_template_submissions')
                    ->whereColumn('pending_template_submissions.connector_id', 'whatsapp_templates.connector_id')
                    ->whereColumn('pending_template_submissions.name', 'whatsapp_templates.name')
                    ->whereColumn('pending_template_submissions.language', 'whatsapp_templates.language')
                    ->where('pending_template_submissions.client_id', $clientId))
                ->orWhereHas('submissions', fn ($submissions) => $submissions->where('client_id', $clientId)->where('review_status', 'approved')->where('provider_status', 'approved')))])
                ->where('client_id', $clientId)->where('enabled', true)->where('approval_status', WhatsAppApprovalStatus::Approved)->get()->pluck('connector.templates')->flatten()->map(fn ($template) => ['id' => $template->id, 'name' => $template->name, 'language' => $template->language, 'category' => $template->category, 'components' => $template->components, 'versions' => $template->versions()->limit(5)->get(['version', 'status', 'captured_at'])]),
            'templateConnectors' => WhatsAppClientConnector::query()->with('connector:id,connector_name,sender_number')->where('client_id', $clientId)->where('enabled', true)->where('approval_status', WhatsAppApprovalStatus::Approved)->get()->map(fn (WhatsAppClientConnector $assignment): array => ['id' => $assignment->connector_id, 'name' => $assignment->connector->connector_name ?: $assignment->connector->sender_number]),
            'templateSubmissions' => WhatsAppTemplateSubmission::query()->where('client_id', $clientId)->with('connector:id,connector_name,sender_number')->latest()->limit(50)->get()->map(fn (WhatsAppTemplateSubmission $submission): array => ['id' => $submission->id, 'name' => $submission->name, 'language' => $submission->language, 'category' => $submission->category, 'components' => $submission->components, 'review_status' => $submission->review_status, 'provider_status' => $submission->provider_status, 'rejection_reason' => $submission->rejection_reason, 'connector' => $submission->connector->connector_name ?: $submission->connector->sender_number]),
            'media' => WhatsAppMediaAsset::query()->where('client_id', $clientId)->latest()->get(['id', 'original_name', 'mime_type', 'size_bytes', 'created_at']),
            'maxUploadKilobytes' => (int) config('whatsapp.media.max_kilobytes', 16384),
            'sendingEnabled' => (bool) config('whatsapp.sending_enabled', false),
            'contacts' => Contact::query()->where('client_id', $clientId)->orderBy('first_name')->limit(500)->get(['id', 'first_name', 'last_name', 'phone']),
            'lists' => ContactList::query()->where('client_id', $clientId)->orderBy('name')->get(['id', 'name']),
            'segments' => ContactSegment::query()->where('client_id', $clientId)->orderBy('name')->get(['id', 'name']),
            'consents' => WhatsAppContactConsent::query()->where('client_id', $clientId)->with('contact:id,client_id,first_name,last_name,phone')->latest()->limit(50)->get(),
            'campaigns' => WhatsAppCampaign::query()->where('client_id', $clientId)->with(['connector:id,connector_name,sender_number', 'template:id,name,language,category'])->latest()->limit(50)->get(),
            'messages' => WhatsAppCampaignRecipient::query()->whereHas('campaign', fn ($query) => $query->where('client_id', $clientId))
                ->with('campaign:id,name')->latest()->limit(250)->get(['id', 'campaign_id', 'phone', 'status', 'provider_message_id', 'submitted_at', 'delivered_at', 'read_at', 'failed_at', 'last_error']),
            'whatsappWallet' => $wallet === null ? null : [
                'currency' => $wallet->currency,
                'balance' => $wallet->balance,
                'reserved' => $wallet->reserved_balance,
                'available' => number_format($wallet->availableBalance(), 4, '.', ''),
                'transactions' => WalletTransaction::query()->where('wallet_id', $wallet->id)->where('metadata->channel', 'whatsapp')->latest()->limit(100)->get(['id', 'type', 'amount', 'balance_after', 'reference', 'description', 'created_at']),
            ],
            'usageReport' => $this->clientUsageReport($clientId),
        ]);
    }

    public function storeTemplateSubmission(StoreWhatsAppTemplateSubmissionRequest $request, WhatsAppAuditLogger $audit): RedirectResponse
    {
        $validated = $request->validated();
        $components = [['type' => 'BODY', 'text' => $validated['body']]];
        $hash = hash('sha256', json_encode([$validated['name'], $validated['language'], $validated['category'], $components], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
        $submission = WhatsAppTemplateSubmission::query()->create([
            'client_id' => $request->user()->client_id,
            'connector_id' => $validated['connector_id'],
            'created_by' => $request->user()->id,
            'name' => $validated['name'],
            'language' => $validated['language'],
            'category' => $validated['category'],
            'components' => $components,
            'content_hash' => $hash,
            'review_status' => 'pending',
            'provider_status' => 'not_submitted',
            'submitted_at' => now(),
        ]);
        $audit->record('template.submitted', $request->user(), $request->user()->client, $submission, ['submission_id' => $submission->id, 'connector_id' => $submission->connector_id]);

        return back()->with('toast', ['type' => 'success', 'message' => 'WhatsApp template submitted for OmniCast review. Provider approval is still required before campaign use.']);
    }

    public function exportMessages(Request $request, WhatsAppAuditLogger $audit): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user->client_id !== null && $user->can('whatsapp.connectors.view'), 403);
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'ready', 'submitting', 'accepted', 'sent', 'delivered', 'read', 'failed', 'unknown', 'ineligible', 'retry_wait'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);
        $query = WhatsAppCampaignRecipient::query()
            ->whereHas('campaign', fn ($campaigns) => $campaigns->where('client_id', $user->client_id))
            ->with('campaign:id,name')
            ->when(isset($filters['status']), fn ($messages) => $messages->where('status', $filters['status']))
            ->when(isset($filters['date_from']), fn ($messages) => $messages->whereDate('created_at', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn ($messages) => $messages->whereDate('created_at', '<=', $filters['date_to']))
            ->orderBy('id');
        $audit->record('messages.exported', $user, $user->client, null, ['filters' => $filters]);

        return response()->streamDownload(function () use ($query): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['Campaign', 'Phone', 'Status', 'Provider message ID', 'Submitted at', 'Delivered at', 'Read at', 'Failed at', 'Last error']);
            $query->chunkById(500, function ($messages) use ($stream): void {
                foreach ($messages as $message) {
                    fputcsv($stream, array_map($this->safeCsvCell(...), [$message->campaign->name, $message->phone, $message->status, $message->provider_message_id, $message->submitted_at?->toIso8601String(), $message->delivered_at?->toIso8601String(), $message->read_at?->toIso8601String(), $message->failed_at?->toIso8601String(), $message->last_error]));
                }
            });
            fclose($stream);
        }, 'whatsapp-messages-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array<string, mixed> */
    private function clientUsageReport(int $clientId): array
    {
        $statusCounts = WhatsAppCampaignRecipient::query()->whereHas('campaign', fn ($campaigns) => $campaigns->where('client_id', $clientId))
            ->select('status', DB::raw('COUNT(*) as total'))->groupBy('status')->pluck('total', 'status');
        $usage = WhatsAppUsageRecord::query()->whereHas('recipient.campaign', fn ($campaigns) => $campaigns->where('client_id', $clientId))
            ->selectRaw('COALESCE(SUM(amount), 0) as charges, COALESCE(SUM(internal_cost), 0) as provider_cost, COUNT(*) as billable_messages')->first();
        $charges = (float) $usage->charges;
        $providerCost = (float) $usage->provider_cost;
        $breakdown = WhatsAppUsageRecord::query()->whereHas('recipient.campaign', fn ($campaigns) => $campaigns->where('client_id', $clientId))
            ->select('category', 'market')->selectRaw('COUNT(*) as messages, COALESCE(SUM(amount), 0) as charges, COALESCE(SUM(internal_cost), 0) as provider_cost')
            ->groupBy('category', 'market')->orderBy('market')->orderBy('category')->get();

        return [
            'status_counts' => $statusCounts,
            'billable_messages' => (int) $usage->billable_messages,
            'charges' => number_format($charges, 4, '.', ''),
            'provider_cost' => number_format($providerCost, 4, '.', ''),
            'gross_margin' => number_format($charges - $providerCost, 4, '.', ''),
            'breakdown' => $breakdown,
        ];
    }

    private function safeCsvCell(mixed $value): string
    {
        $cell = (string) ($value ?? '');

        return preg_match('/^\s*[=+\-@\t\r]/u', $cell) === 1 ? "'".$cell : $cell;
    }

    public function storeConsent(StoreWhatsAppConsentRequest $request, WhatsAppAuditLogger $audit): RedirectResponse
    {
        $consent = WhatsAppContactConsent::query()->create([
            ...$request->validated(),
            'client_id' => $request->user()->client_id,
            'recorded_by' => $request->user()->id,
        ]);
        $audit->record('consent.granted', $request->user(), $request->user()->client, $consent, ['consent_id' => $consent->id, 'purpose' => $consent->purpose]);

        return back()->with('toast', ['type' => 'success', 'message' => 'WhatsApp consent evidence recorded.']);
    }

    public function revokeConsent(WhatsAppContactConsent $consent, WhatsAppAuditLogger $audit): RedirectResponse
    {
        abort_unless((int) $consent->client_id === (int) request()->user()->client_id, 404);
        $consent->forceFill(['revoked_at' => now()])->save();
        $audit->record('consent.revoked', request()->user(), request()->user()->client, $consent, ['consent_id' => $consent->id]);

        return back()->with('toast', ['type' => 'success', 'message' => 'WhatsApp consent was revoked.']);
    }

    public function storeCampaign(StoreWhatsAppCampaignRequest $request, WhatsAppCampaignBuilder $builder): RedirectResponse
    {
        $campaign = $builder->create($request->validated(), $request->user());

        return to_route('whatsapp.index')->with('toast', ['type' => 'success', 'message' => "Draft {$campaign->name} saved with {$campaign->estimated_recipients} eligible recipient(s). Sending is not enabled yet."]);
    }

    public function dispatchCampaign(WhatsAppCampaign $campaign, WhatsAppCampaignControl $control): RedirectResponse
    {
        $this->authorizeCampaign($campaign);
        abort_unless(config('whatsapp.sending_enabled', false), 423, 'WhatsApp sending is disabled.');
        abort_unless($campaign->status === 'draft', 409, 'Only a draft campaign can be started.');
        if ($campaign->schedule_type === 'scheduled' && $campaign->scheduled_at?->isFuture()) {
            $campaign->forceFill(['status' => 'scheduled', 'control_state' => 'active'])->save();

            return back()->with('toast', ['type' => 'success', 'message' => 'Campaign scheduled.']);
        }
        PrepareWhatsAppCampaign::dispatch((int) $campaign->id)->onQueue((string) config('queues.channels.whatsapp.queue', 'whatsapp'));

        return back()->with('toast', ['type' => 'success', 'message' => 'WhatsApp campaign validation and dispatch queued.']);
    }

    public function testCampaign(WhatsAppCampaign $campaign, WhatsAppCampaignControl $control): RedirectResponse
    {
        $this->authorizeCampaign($campaign);
        abort_unless(config('whatsapp.sending_enabled', false), 423, 'WhatsApp sending is disabled.');
        abort_unless($campaign->status === 'draft', 409, 'Only a draft campaign can be test-sent.');
        PrepareWhatsAppCampaign::dispatch((int) $campaign->id, true)->onQueue((string) config('queues.channels.whatsapp.queue', 'whatsapp'));

        return back()->with('toast', ['type' => 'success', 'message' => 'Controlled test-send validation queued.']);
    }

    public function pauseCampaign(WhatsAppCampaign $campaign, WhatsAppCampaignControl $control): RedirectResponse
    {
        $this->authorizeCampaign($campaign);
        $control->pause($campaign, request()->user());

        return back()->with('toast', ['type' => 'success', 'message' => 'Campaign paused.']);
    }

    public function resumeCampaign(WhatsAppCampaign $campaign, WhatsAppCampaignControl $control): RedirectResponse
    {
        $this->authorizeCampaign($campaign);
        $control->resume($campaign, request()->user());

        return back()->with('toast', ['type' => 'success', 'message' => 'Campaign resumed.']);
    }

    public function cancelCampaign(WhatsAppCampaign $campaign, WhatsAppCampaignControl $control): RedirectResponse
    {
        $this->authorizeCampaign($campaign);
        $control->cancel($campaign, request()->user());

        return back()->with('toast', ['type' => 'success', 'message' => 'Campaign cancelled.']);
    }

    private function authorizeCampaign(WhatsAppCampaign $campaign): void
    {
        abort_unless((int) $campaign->client_id === (int) request()->user()->client_id && request()->user()->can('whatsapp.connectors.view'), 404);
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
