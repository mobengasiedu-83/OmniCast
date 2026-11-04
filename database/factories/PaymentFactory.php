<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Client;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
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
            'wallet_id' => null,
            'user_id' => null,
            'reference' => 'OC-PAY-'.Str::upper(Str::random(10)),
            'provider' => 'paystack',
            'provider_reference' => null,
            'status' => PaymentStatus::Pending,
            'currency' => 'GHS',
            'amount' => 51.25,
            'amount_paid' => 0,
            'package_key' => 'growth',
            'description' => 'Growth package top up',
            'channel' => null,
            'authorization_url' => 'https://checkout.paystack.com/'.Str::random(12),
            'access_code' => Str::random(12),
            'failure_reason' => null,
            'metadata' => [],
            'paid_at' => null,
        ];
    }

    /**
     * Indicate that the gateway settled the payment.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PaymentStatus::Paid,
            'amount_paid' => $attributes['amount'],
            'channel' => 'mobile_money',
            'paid_at' => now(),
        ]);
    }

    /**
     * Indicate that the gateway refused the payment.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PaymentStatus::Failed,
            'failure_reason' => 'Insufficient funds',
        ]);
    }

    /**
     * Indicate that the top up was made outside a package.
     */
    public function custom(float $amount = 20.0): static
    {
        return $this->state(fn (array $attributes): array => [
            'amount' => $amount,
            'package_key' => null,
            'description' => 'Wallet top up',
        ]);
    }
}
