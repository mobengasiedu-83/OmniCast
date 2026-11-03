<?php

namespace Database\Factories;

use App\Enums\InvitationStatus;
use App\Enums\Role;
use App\Models\Client;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<UserInvitation>
 */
class UserInvitationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => null,
            'email' => fake()->unique()->safeEmail(),
            'name' => fake()->name(),
            'role' => Role::ClientOperator->value,
            'token' => UserInvitation::hashToken(Str::random(64)),
            'status' => InvitationStatus::Pending,
            'invited_by' => null,
            'expires_at' => now()->addDays(UserInvitation::VALID_DAYS),
            'last_sent_at' => now(),
        ];
    }

    /**
     * Indicate that the invitation is for a client tenant.
     */
    public function forClient(Client $client): static
    {
        return $this->state(fn (array $attributes) => [
            'client_id' => $client->id,
        ]);
    }

    /**
     * Indicate that the invitation has lapsed.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDay(),
        ]);
    }

    /**
     * Indicate that the invitation was already accepted.
     */
    public function accepted(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InvitationStatus::Accepted,
            'accepted_by' => $user->id,
            'accepted_at' => now(),
        ]);
    }

    /**
     * Indicate that the invitation was withdrawn.
     */
    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InvitationStatus::Revoked,
            'revoked_at' => now(),
        ]);
    }
}
