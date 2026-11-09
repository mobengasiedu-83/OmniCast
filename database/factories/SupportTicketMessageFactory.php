<?php

namespace Database\Factories;

use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportTicketMessage>
 */
class SupportTicketMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'support_ticket_id' => SupportTicket::factory(),
            'user_id' => null,
            'author_name' => $this->faker->name(),
            'author_email' => $this->faker->safeEmail(),
            'body' => $this->faker->paragraph(),
            'is_internal' => false,
            'source' => 'requester',
        ];
    }

    /**
     * A reply written by the support team.
     */
    public function fromAgent(): static
    {
        return $this->state(fn (): array => ['source' => 'agent']);
    }

    /**
     * A note only the team can read.
     */
    public function internal(): static
    {
        return $this->state(fn (): array => [
            'source' => 'agent',
            'is_internal' => true,
        ]);
    }
}
