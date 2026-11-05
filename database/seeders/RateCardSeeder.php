<?php

namespace Database\Seeders;

use App\Enums\BillingUnit;
use App\Enums\Channel;
use App\Models\Rate;
use App\Models\RateCard;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * The platform-wide price list.
 *
 * A client that has no rate card of its own is billed against these rates, so
 * every service is priced rather than silently broadcast for free. The prices
 * mirror the published price list: GHS 0.04 an SMS part, GHS 0.38 a voice
 * minute and GHS 0.05 an email.
 */
class RateCardSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * The platform default card and rate for each billable service.
     *
     * @var list<array{channel: Channel, name: string, unit: BillingUnit, price: float, cost: float}>
     */
    protected array $services = [
        ['channel' => Channel::Sms, 'name' => 'Platform SMS Rates', 'unit' => BillingUnit::PerSmsPart, 'price' => 0.0400, 'cost' => 0.0250],
        ['channel' => Channel::Voice, 'name' => 'Platform Voice Rates', 'unit' => BillingUnit::PerMinute, 'price' => 0.3800, 'cost' => 0.1200],
        ['channel' => Channel::Email, 'name' => 'Platform Email Rates', 'unit' => BillingUnit::PerEmail, 'price' => 0.0500, 'cost' => 0.0100],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->services as $service) {
            $rateCard = RateCard::query()->firstOrCreate(
                ['client_id' => null, 'channel' => $service['channel']->value],
                [
                    'name' => $service['name'],
                    'currency' => (string) config('billing.currency', 'GHS'),
                    'is_default' => true,
                    'effective_from' => now()->startOfYear()->toDateString(),
                    'effective_to' => null,
                ],
            );

            Rate::query()->firstOrCreate(
                ['rate_card_id' => $rateCard->id, 'channel' => $service['channel']->value, 'destination' => 'GH'],
                [
                    'destination_label' => 'Ghana',
                    'billing_unit' => $service['unit'],
                    'unit_price' => $service['price'],
                    'supplier_cost' => $service['cost'],
                ],
            );
        }
    }
}
