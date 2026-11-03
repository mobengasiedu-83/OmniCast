<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Wallet>
 */
class WalletFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'currency' => 'GHS',
            'balance' => 500,
            'reserved_balance' => 0,
            'low_balance_threshold' => 50,
        ];
    }
}
