<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreWhatsAppPricingRateRequest;
use App\Models\Client;
use App\Models\WhatsAppPricingRate;
use App\Services\WhatsApp\WhatsAppAuditLogger;
use App\Services\WhatsApp\WhatsAppPricingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WhatsAppPricingRateController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('rates.view'), 403);

        return Inertia::render('admin/whatsapp/pricing', [
            'rates' => WhatsAppPricingRate::query()->with('client:id,name')->latest()->limit(200)->get(),
            'clients' => Client::query()->orderBy('name')->get(['id', 'name']),
            'canManage' => $request->user()->can('rates.manage'),
        ]);
    }

    public function store(StoreWhatsAppPricingRateRequest $request, WhatsAppPricingService $pricing, WhatsAppAuditLogger $audit): RedirectResponse
    {
        $data = $request->validated();
        $data['market'] = strtoupper($data['market']);
        $data['currency'] = 'USD';
        $data['selling_price'] = $pricing->calculateSellingPrice(
            (float) $data['meta_rate'], (float) $data['provider_fee'], (float) $data['direct_cost'],
            (float) $data['fx_rate'], (float) $data['markup_percent'], (float) $data['minimum_price'],
        );
        $data['status'] = 'pending';
        $data['created_by'] = $request->user()->id;
        $rate = WhatsAppPricingRate::query()->create($data);
        $audit->record('pricing.rate_created', $request->user(), null, $rate, ['rate_id' => $rate->id, 'market' => $rate->market, 'category' => $rate->category, 'selling_price' => $rate->selling_price]);

        return back()->with('toast', ['type' => 'success', 'message' => 'WhatsApp rate saved pending approval.']);
    }

    public function approve(Request $request, WhatsAppPricingRate $rate, WhatsAppAuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->can('rates.manage'), 403);
        abort_unless($rate->status === 'pending', 409);
        $rate->forceFill(['status' => 'approved', 'approved_by' => $request->user()->id, 'approved_at' => now()])->save();
        $audit->record('pricing.rate_approved', $request->user(), $rate->client, $rate, ['rate_id' => $rate->id, 'market' => $rate->market, 'category' => $rate->category, 'selling_price' => $rate->selling_price]);

        return back()->with('toast', ['type' => 'success', 'message' => 'WhatsApp rate approved.']);
    }
}
