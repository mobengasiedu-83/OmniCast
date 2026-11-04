<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = 51.25;

        return [
            'client_id' => Client::factory(),
            'payment_id' => null,
            'number' => 'INV-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
            'status' => InvoiceStatus::Paid,
            'currency' => 'GHS',
            'subtotal' => $amount,
            'tax' => 0,
            'total' => $amount,
            'lines' => [
                [
                    'description' => 'Growth package',
                    'quantity' => 1,
                    'unit_price' => $amount,
                    'amount' => $amount,
                ],
            ],
            'metadata' => [],
            'issued_at' => now(),
            'paid_at' => now(),
        ];
    }

    /**
     * Attach the invoice to the payment that settled it.
     */
    public function forPayment(Payment $payment): static
    {
        return $this->state(fn (array $attributes): array => [
            'client_id' => $payment->client_id,
            'payment_id' => $payment->id,
            'currency' => $payment->currency,
            'subtotal' => (float) $payment->amount,
            'total' => (float) $payment->amount,
        ]);
    }

    /**
     * Indicate that the invoice was voided after a refund.
     */
    public function voided(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => InvoiceStatus::Void,
        ]);
    }
}
