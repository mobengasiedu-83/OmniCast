<?php

namespace App\Services;

use App\Enums\Channel;
use App\Models\Client;
use App\Models\Rate;
use Illuminate\Database\Eloquent\Builder;

/**
 * Resolves the rate a client is billed at for a given channel and destination.
 */
class RateResolver
{
    /**
     * Resolve the rate for the client, falling back to the platform default card.
     */
    public function resolve(Client $client, Channel $channel, string $destination = 'GH'): ?Rate
    {
        foreach ([$client->id, null] as $clientId) {
            $rate = Rate::query()
                ->where('channel', $channel->value)
                ->where('destination', $destination)
                ->whereHas('rateCard', fn (Builder $query): Builder => $this->constrainRateCard($query, $clientId))
                ->orderByDesc('id')
                ->first();

            if ($rate !== null) {
                return $rate;
            }
        }

        return null;
    }

    /**
     * Resolve the unit price for the client, or null when no rate is configured.
     */
    public function unitPrice(Client $client, Channel $channel, string $destination = 'GH'): ?float
    {
        $rate = $this->resolve($client, $channel, $destination);

        return $rate === null ? null : (float) $rate->unit_price;
    }

    /**
     * Constrain the rate card query to the given client scope (or the platform default).
     *
     * @param  Builder<\App\Models\RateCard>  $query
     * @return Builder<\App\Models\RateCard>
     */
    protected function constrainRateCard(Builder $query, ?int $clientId): Builder
    {
        return $query
            ->where('client_id', $clientId)
            ->where('is_default', true)
            ->where(fn (Builder $query): Builder => $query->whereNull('effective_from')->orWhere('effective_from', '<=', now()))
            ->where(fn (Builder $query): Builder => $query->whereNull('effective_to')->orWhere('effective_to', '>=', now()));
    }
}
