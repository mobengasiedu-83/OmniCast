<?php

namespace Database\Factories;

use App\Enums\SmppMessageStatus;
use App\Enums\SmsEncoding;
use App\Models\Client;
use App\Models\SmppAccount;
use App\Models\SmppMessage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SmppMessage>
 */
class SmppMessageFactory extends Factory
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
            'smpp_account_id' => SmppAccount::factory(),
            'smpp_session_id' => null,
            'reference' => 'SMPP-'.Str::upper(Str::random(12)),
            'message_id' => (string) Str::ulid(),
            'source_address' => 'OMNICAST',
            'destination_address' => '+2332'.fake()->numerify('#######'),
            'body' => 'Your statement is ready.',
            'encoding' => SmsEncoding::Gsm7,
            'segments' => 1,
            'status' => SmppMessageStatus::Accepted,
            'provider' => 'log',
            'submitted_at' => now(),
        ];
    }

    /**
     * Indicate that the carrier confirmed delivery.
     */
    public function delivered(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SmppMessageStatus::Delivered,
            'delivered_at' => now(),
        ]);
    }

    /**
     * Indicate that the carrier could not deliver the message.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SmppMessageStatus::Failed,
            'failed_at' => now(),
            'error_message' => 'The handset is switched off.',
        ]);
    }
}
