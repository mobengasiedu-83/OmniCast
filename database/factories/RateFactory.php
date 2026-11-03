<?php

namespace Database\Factories;

use App\Enums\BillingUnit;
use App\Enums\Channel;
use App\Models\Rate;
use App\Models\RateCard;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rate>
 */
class RateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rate_card_id' => RateCard::factory(),
            'channel' => Channel::Sms,
            'destination' => 'GH',
            'destination_label' => 'Ghana',
            'billing_unit' => BillingUnit::PerSmsPart,
            'unit_price' => 0.0350,
            'supplier_cost' => 0.0250,
        ];
    }
}
