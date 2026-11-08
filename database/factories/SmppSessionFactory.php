<?php

namespace Database\Factories;

use App\Enums\SmppBindMode;
use App\Models\SmppAccount;
use App\Models\SmppSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SmppSession>
 */
class SmppSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'smpp_account_id' => SmppAccount::factory(),
            'system_id' => fn (array $attributes): string => (string) SmppAccount::query()
                ->find($attributes['smpp_account_id'])?->system_id,
            'mode' => SmppBindMode::Transceiver,
            'remote_address' => '127.0.0.1:'.fake()->numberBetween(20000, 60000),
            'bound_at' => now(),
            'last_seen_at' => now(),
            'pdus_in' => 1,
            'pdus_out' => 1,
        ];
    }

    /**
     * Indicate that the session has been closed.
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'unbound_at' => now(),
        ]);
    }
}
