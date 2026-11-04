<?php

namespace Database\Factories;

use App\Enums\SenderIdChannel;
use App\Enums\SenderIdStatus;
use App\Models\Client;
use App\Models\SenderId;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SenderId>
 */
class SenderIdFactory extends Factory
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
            'channel' => SenderIdChannel::Sms,
            'name' => Str::upper(fake()->unique()->bothify('BRAND##')),
            'country' => 'GH',
            'purpose' => fake()->sentence(6),
            'status' => SenderIdStatus::Pending,
            'requested_at' => now(),
        ];
    }

    /**
     * Indicate that the sender ID is an SMS brand name.
     */
    public function sms(): static
    {
        return $this->state(fn (array $attributes): array => [
            'channel' => SenderIdChannel::Sms,
            'name' => Str::upper(fake()->unique()->bothify('BRAND##')),
            'country' => 'GH',
        ]);
    }

    /**
     * Indicate that the sender ID is an email from address.
     */
    public function email(): static
    {
        return $this->state(fn (array $attributes): array => [
            'channel' => SenderIdChannel::Email,
            'name' => fake()->unique()->safeEmail(),
            'country' => null,
        ]);
    }

    /**
     * Indicate that the sender ID is a voice caller number.
     */
    public function voice(): static
    {
        return $this->state(fn (array $attributes): array => [
            'channel' => SenderIdChannel::Voice,
            'name' => '+233'.fake()->unique()->numerify('#########'),
            'country' => 'GH',
        ]);
    }

    /**
     * Indicate that the sender ID has been approved by compliance.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SenderIdStatus::Approved,
            'submitted_at' => now(),
            'approved_at' => now(),
        ]);
    }

    /**
     * Indicate that the sender ID was rejected during review.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SenderIdStatus::Rejected,
            'rejection_reason' => 'Brand does not match the registered organisation.',
        ]);
    }
}
