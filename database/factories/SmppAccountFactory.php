<?php

namespace Database\Factories;

use App\Enums\SmppBindMode;
use App\Models\Client;
use App\Models\SmppAccount;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<SmppAccount>
 */
class SmppAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * The plaintext password is `smpp-pass`, which is what the bind tests use.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'name' => fake()->company(),
            'system_id' => 'ESME'.Str::upper(Str::random(4)),
            'password_hash' => Hash::make('smpp-pass'),
            'bind_mode' => SmppBindMode::Transceiver,
            'allowed_sources' => null,
            'throughput_per_second' => 10,
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the account may only submit.
     */
    public function transmitter(): static
    {
        return $this->state(fn (array $attributes): array => [
            'bind_mode' => SmppBindMode::Transmitter,
        ]);
    }

    /**
     * Indicate that the account has been switched off.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }
}
