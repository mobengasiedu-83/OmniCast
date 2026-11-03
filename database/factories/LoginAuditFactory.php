<?php

namespace Database\Factories;

use App\Enums\LoginEvent;
use App\Models\Client;
use App\Models\LoginAudit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoginAudit>
 */
class LoginAuditFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'client_id' => null,
            'email' => fake()->unique()->safeEmail(),
            'event' => LoginEvent::LoginSucceeded,
            'guard' => 'web',
            'ip_address' => fake()->ipv4(),
            'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Safari/537.36',
            'context' => null,
            'created_at' => now(),
        ];
    }

    /**
     * Indicate that the attempt was rejected.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'event' => LoginEvent::LoginFailed,
            'user_id' => null,
        ]);
    }

    /**
     * Attach the audit entry to a user and their tenant.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
            'client_id' => $user->client_id,
            'email' => $user->email,
        ]);
    }

    /**
     * Attach the audit entry to a client tenant.
     */
    public function forClient(Client $client): static
    {
        return $this->state(fn (array $attributes) => [
            'client_id' => $client->id,
        ]);
    }
}
