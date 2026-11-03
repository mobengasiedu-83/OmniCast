<?php

namespace Database\Factories;

use App\Enums\ReservationStatus;
use App\Models\BillingReservation;
use App\Models\Campaign;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BillingReservation>
 */
class BillingReservationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'wallet_id' => Wallet::factory(),
            'amount' => 35,
            'captured_amount' => 0,
            'released_amount' => 0,
            'status' => ReservationStatus::Held,
        ];
    }
}
