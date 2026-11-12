<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WalletTransaction;
use App\Models\WhatsAppInvoiceReconciliation;
use App\Models\WhatsAppMessageAttempt;
use App\Models\WhatsAppProviderEvent;
use App\Models\WhatsAppProviderInvoice;
use App\Models\WhatsAppUsageRecord;
use App\Services\WhatsApp\WhatsAppAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class WhatsAppReconciliationController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('admin/whatsapp/reconciliation', [
            'invoices' => WhatsAppProviderInvoice::query()->with('provider:id,name')->latest()->limit(100)->get(),
            'variances' => WhatsAppInvoiceReconciliation::query()->with('invoice:id,invoice_number,provider_id')->where('status', 'review_required')->latest()->limit(500)->get(),
            'events' => WhatsAppProviderEvent::query()->with('provider:id,name')->latest()->limit(200)->get(),
            'usageRecords' => WhatsAppUsageRecord::query()->with(['provider:id,name', 'invoice:id,invoice_number'])->latest()->limit(500)->get(),
            'attempts' => WhatsAppMessageAttempt::query()->with('recipient:id,campaign_id,phone')->latest()->limit(500)->get(),
            'ledger' => WalletTransaction::query()->with('wallet.client:id,name')->where('metadata->channel', 'whatsapp')->latest()->limit(500)->get(),
            'canReview' => $request->user()->can('rates.manage'),
        ]);
    }

    public function review(Request $request, WhatsAppInvoiceReconciliation $reconciliation, WhatsAppAuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->can('rates.manage'), 403);
        $data = $request->validate(['review_note' => ['required', 'string', 'max:2000']]);
        DB::transaction(function () use ($request, $reconciliation, $data): void {
            $locked = WhatsAppInvoiceReconciliation::query()->lockForUpdate()->findOrFail($reconciliation->id);
            if ($locked->status !== 'review_required') {
                throw ValidationException::withMessages(['reconciliation' => 'Only open reconciliation variances can be reviewed.']);
            }
            $locked->forceFill(['status' => 'reviewed', 'review_note' => $data['review_note'], 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()])->save();
            $invoice = WhatsAppProviderInvoice::query()->lockForUpdate()->findOrFail($locked->invoice_id);
            if (! $invoice->reconciliations()->where('status', 'review_required')->exists()) {
                $invoice->forceFill(['status' => 'reviewed'])->save();
            }
            $audit->record('billing.invoice_variance_reviewed', $request->user(), null, $locked, [
                'invoice_id' => $invoice->id,
                'variance' => $locked->variance,
                'review_note' => $data['review_note'],
            ]);
        });

        return back()->with('toast', ['type' => 'success', 'message' => 'Variance marked reviewed with the recorded decision.']);
    }
}
