<?php

namespace App\Services\WhatsApp;

use App\Models\Client;
use App\Models\WhatsAppPricingRate;
use App\Models\WhatsAppTemplate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class WhatsAppPricingService
{
    public function estimate(Client $client, WhatsAppTemplate $template, string $phone, int $volume = 1): array
    {
        $market = $this->marketFor($phone);
        $category = $this->categoryFor($template->category);
        $rate = $this->resolve($client, $market, $category, $volume);
        if ($rate === null) {
            throw ValidationException::withMessages(['pricing' => "No approved effective WhatsApp rate exists for {$market}/{$category}."]);
        }

        return ['price' => (float) $rate->selling_price, 'snapshot' => $rate->inputsSnapshot()];
    }

    public function resolve(Client $client, string $market, string $category, int $volume = 1): ?WhatsAppPricingRate
    {
        foreach ([$client->id, null] as $clientId) {
            $rate = WhatsAppPricingRate::query()->where('client_id', $clientId)->where('market', $market)
                ->where('category', $category)->where('status', 'approved')
                ->where('volume_from', '<=', max($volume, 1))
                ->where(fn (Builder $query) => $query->whereNull('volume_to')->orWhere('volume_to', '>=', max($volume, 1)))
                ->whereDate('effective_from', '<=', today())
                ->where(fn (Builder $query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', today()))
                ->orderByDesc('effective_from')->orderByDesc('volume_from')->orderByDesc('id')->first();
            if ($rate !== null) {
                return $rate;
            }
        }

        return null;
    }

    public function marketFor(string $phone): string
    {
        $digits = ltrim(preg_replace('/\D+/', '', $phone) ?? '', '0');
        $prefixes = ['233' => 'GH', '234' => 'NG', '254' => 'KE', '27' => 'ZA', '1' => 'US', '44' => 'GB'];
        foreach ($prefixes as $prefix => $market) {
            if (str_starts_with($digits, $prefix)) {
                return $market;
            }
        }

        return 'OTHER';
    }

    public function categoryFor(?string $category): string
    {
        $normalized = strtolower((string) $category);
        if (in_array($normalized, ['marketing', 'utility', 'authentication'], true)) {
            return $normalized;
        }

        throw ValidationException::withMessages(['pricing' => 'The template category has no approved WhatsApp price mapping.']);
    }

    public function calculateSellingPrice(float $metaRate, float $providerFee, float $directCost, float $fxRate, float $markupPercent, float $minimumPrice): float
    {
        $baseCost = ($metaRate * $fxRate) + $providerFee + $directCost;

        return round(max($baseCost * (1 + ($markupPercent / 100)), $minimumPrice), 4);
    }
}
