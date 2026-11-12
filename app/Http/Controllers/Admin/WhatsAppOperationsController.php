<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppCampaignRecipient;
use App\Models\WhatsAppContactConsent;
use App\Models\WhatsAppInvoiceReconciliation;
use App\Models\WhatsAppProvider;
use App\Models\WhatsAppProviderEvent;
use App\Models\WhatsAppProviderInvoice;
use App\Models\WhatsAppUsageRecord;
use App\Models\WhatsAppWalletReservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class WhatsAppOperationsController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('whatsapp.providers.view'), 403);
        $recipientStatuses = WhatsAppCampaignRecipient::query()->select('status', DB::raw('COUNT(*) as total'))->groupBy('status')->pluck('total', 'status');
        $campaignStatuses = WhatsAppCampaign::query()->select('status', DB::raw('COUNT(*) as total'))->groupBy('status')->pluck('total', 'status');
        $usage = WhatsAppUsageRecord::query()->selectRaw('COALESCE(SUM(amount), 0) as charges, COALESCE(SUM(internal_cost), 0) as cost, COUNT(*) as billable')->first();
        $consents = WhatsAppContactConsent::query()->select('purpose', DB::raw('COUNT(*) as total'), DB::raw('SUM(CASE WHEN revoked_at IS NULL THEN 1 ELSE 0 END) as active'), DB::raw('SUM(CASE WHEN revoked_at IS NOT NULL THEN 1 ELSE 0 END) as revoked'))->groupBy('purpose')->get();

        return Inertia::render('admin/whatsapp/operations', [
            'sendingEnabled' => (bool) config('whatsapp.sending_enabled', false),
            'eventsEnabled' => (bool) config('whatsapp.events.contract_enabled', false),
            'providerHealth' => WhatsAppProvider::query()->withCount('connectors')->latest()->get(['id', 'name', 'driver', 'status', 'is_enabled', 'tested_at', 'last_test_error']),
            'campaignStatuses' => $campaignStatuses,
            'recipientStatuses' => $recipientStatuses,
            'unknownReservations' => [
                'open' => WhatsAppWalletReservation::query()->where('status', 'held')->whereHas('campaign.recipients', fn ($recipients) => $recipients->where('status', 'unknown'))->count(),
                'overdue' => WhatsAppWalletReservation::query()->whereNotNull('exception_flagged_at')->where('status', 'held')->count(),
                'holdDays' => (int) config('whatsapp.billing.unknown_hold_days', 30),
            ],
            'eventQueue' => [
                'unmatched' => WhatsAppProviderEvent::query()->where('processing_status', 'unmatched')->count(),
                'unverified' => WhatsAppProviderEvent::query()->where('signature_verified', false)->count(),
            ],
            'reconciliation' => [
                'openVariances' => WhatsAppInvoiceReconciliation::query()->where('status', 'review_required')->count(),
                'invoicesToReview' => WhatsAppProviderInvoice::query()->where('status', 'review_required')->count(),
            ],
            'usage' => [
                'billableMessages' => (int) $usage->billable,
                'charges' => number_format((float) $usage->charges, 4, '.', ''),
                'providerCost' => number_format((float) $usage->cost, 4, '.', ''),
                'grossMargin' => number_format((float) $usage->charges - (float) $usage->cost, 4, '.', ''),
            ],
            'consents' => $consents,
            'recentCampaigns' => WhatsAppCampaign::query()->with('client:id,name')->latest()->limit(50)->get(['id', 'client_id', 'name', 'status', 'estimated_recipients', 'created_at']),
            'recentMessages' => WhatsAppCampaignRecipient::query()->with('campaign.client:id,name')->latest()->limit(100)->get(['id', 'campaign_id', 'status', 'provider_message_id', 'created_at']),
            'launchFlags' => [
                'inboundMessaging' => false,
                'readReceipts' => (bool) config('whatsapp.events.contract_enabled', false),
                'providerTemplateSubmission' => false,
                'productionBillingEvidence' => (bool) config('whatsapp.events.contract_enabled', false),
            ],
        ]);
    }
}
