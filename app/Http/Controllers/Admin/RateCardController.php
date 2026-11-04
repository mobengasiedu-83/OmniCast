<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Channel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\UpdateRateRequest;
use App\Models\Client;
use App\Models\Rate;
use App\Models\RateCard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The rate cards a campaign is billed against, and the prices on them.
 */
class RateCardController extends Controller
{
    /**
     * List the rate cards with the prices on each one.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', RateCard::class);

        $filters = [
            'channel' => $request->string('channel')->toString(),
            'client_id' => $request->string('client_id')->toString(),
        ];

        $rateCards = RateCard::query()
            ->with(['client:id,name', 'rates'])
            ->when($filters['channel'] !== '', fn ($query) => $query->where('channel', $filters['channel']))
            ->when($filters['client_id'] === 'platform', fn ($query) => $query->whereNull('client_id'))
            ->when(
                $filters['client_id'] !== '' && $filters['client_id'] !== 'platform',
                fn ($query) => $query->where('client_id', (int) $filters['client_id']),
            )
            ->when($request->user()->client_id !== null, fn ($query) => $query->where('client_id', $request->user()->client_id))
            ->orderBy('client_id')
            ->orderBy('channel')
            ->orderBy('name')
            ->get()
            ->map(fn (RateCard $rateCard): array => $this->row($rateCard))
            ->all();

        return Inertia::render('admin/billing/rate-cards', [
            'rateCards' => $rateCards,
            'filters' => $filters,
            'channels' => $this->channelOptions(),
            'clients' => $this->clientOptions(),
            'stats' => [
                'cards' => count($rateCards),
                'rates' => array_sum(array_map(fn (array $card): int => count($card['rates']), $rateCards)),
            ],
            'can' => [
                'manage' => $request->user()->can('rates.manage'),
            ],
        ]);
    }

    /**
     * Change what one destination is billed at.
     */
    public function updateRate(UpdateRateRequest $request, Rate $rate): RedirectResponse
    {
        Gate::authorize('update', $rate->rateCard);

        $rate->update([
            'unit_price' => round((float) $request->float('unit_price'), 4),
            'supplier_cost' => round((float) $request->float('supplier_cost'), 4),
        ]);

        return back()->with('toast', [
            'type' => 'success',
            'message' => "{$rate->destination} now bills at ".number_format((float) $rate->unit_price, 4).'.',
        ]);
    }

    /**
     * Present a rate card with its rates for the page.
     *
     * @return array<string, mixed>
     */
    protected function row(RateCard $rateCard): array
    {
        return [
            'id' => $rateCard->id,
            'name' => $rateCard->name,
            'client_id' => $rateCard->client_id,
            'client' => $rateCard->client?->name ?? 'Platform default',
            'channel' => $rateCard->channel->value,
            'channel_label' => $rateCard->channel->label(),
            'currency' => $rateCard->currency,
            'is_default' => $rateCard->is_default,
            'effective_from' => $rateCard->effective_from?->toDateString(),
            'effective_to' => $rateCard->effective_to?->toDateString(),
            'rates' => $rateCard->rates
                ->sortBy('destination')
                ->map(fn (Rate $rate): array => [
                    'id' => $rate->id,
                    'destination' => $rate->destination,
                    'destination_label' => $rate->destination_label ?? $rate->destination,
                    'billing_unit' => $rate->billing_unit->value,
                    'billing_unit_label' => $rate->billing_unit->label(),
                    'unit_price' => (float) $rate->unit_price,
                    'supplier_cost' => (float) $rate->supplier_cost,
                    'margin' => $rate->margin(),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * The channels a rate card can cover.
     *
     * @return list<array{value: string, label: string}>
     */
    protected function channelOptions(): array
    {
        return array_map(
            fn (Channel $channel): array => ['value' => $channel->value, 'label' => $channel->label()],
            Channel::cases(),
        );
    }

    /**
     * The client accounts an operator may filter on.
     *
     * @return list<array{id: int, name: string}>
     */
    protected function clientOptions(): array
    {
        return Client::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Client $client): array => ['id' => $client->id, 'name' => $client->name])
            ->all();
    }
}
