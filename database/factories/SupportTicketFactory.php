<?php

namespace Database\Factories;

use App\Enums\SupportPriority;
use App\Enums\SupportTicketStatus;
use App\Enums\SupportTicketType;
use App\Models\Client;
use App\Models\SupportCategory;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SupportTicket>
 */
class SupportTicketFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->name();
        $subject = $this->faker->sentence(4);

        return [
            'reference' => 'TKT-'.Str::upper($this->faker->unique()->bothify('######')),
            'type' => SupportTicketType::Ticket,
            'source' => 'client',
            'support_category_id' => null,
            'support_team_id' => null,
            'client_id' => null,
            'user_id' => null,
            'name' => $name,
            'email' => $this->faker->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'company' => null,
            'subject' => $subject,
            'description' => $this->faker->paragraphs(2, true),
            'priority' => SupportPriority::Normal,
            'status' => SupportTicketStatus::New,
            'assigned_to' => null,
            'campaign_id' => null,
            'wallet_transaction_id' => null,
            'consent_at' => now(),
            'ip_address' => '102.68.10.4',
            'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
            'first_responded_at' => null,
            'resolved_at' => null,
            'closed_at' => null,
            'last_reply_at' => null,
            'metadata' => null,
        ];
    }

    /**
     * A conversation opened on the public contact form.
     */
    public function enquiry(): static
    {
        return $this->state(fn (): array => [
            'reference' => 'ENQ-'.Str::upper($this->faker->unique()->bothify('######')),
            'type' => SupportTicketType::Enquiry,
            'source' => 'public',
            'client_id' => null,
            'user_id' => null,
        ]);
    }

    /**
     * A conversation opened by a signed-in client.
     */
    public function forClient(Client $client, ?User $requester = null): static
    {
        return $this->state(fn (): array => [
            'type' => SupportTicketType::Ticket,
            'source' => 'client',
            'client_id' => $client->id,
            'user_id' => $requester?->id,
        ]);
    }

    /**
     * File the conversation under a category.
     */
    public function category(SupportCategory $category): static
    {
        return $this->state(fn (): array => [
            'support_category_id' => $category->id,
            'support_team_id' => $category->support_team_id,
            'priority' => $category->default_priority,
        ]);
    }

    /**
     * A conversation an agent has already replied to.
     */
    public function answered(): static
    {
        return $this->state(fn (): array => [
            'status' => SupportTicketStatus::Open,
            'first_responded_at' => now()->subHour(),
            'last_reply_at' => now()->subHour(),
        ]);
    }

    /**
     * A conversation that has been settled.
     */
    public function resolved(): static
    {
        return $this->state(fn (): array => [
            'status' => SupportTicketStatus::Resolved,
            'resolved_at' => now()->subDay(),
            'last_reply_at' => now()->subDay(),
        ]);
    }

    /**
     * A conversation that is waiting on the requester.
     */
    public function pending(): static
    {
        return $this->state(fn (): array => ['status' => SupportTicketStatus::Pending]);
    }

    /**
     * A conversation the team must answer first.
     */
    public function urgent(): static
    {
        return $this->state(fn (): array => ['priority' => SupportPriority::Urgent]);
    }

    /**
     * Give the conversation an owner.
     */
    public function assignedTo(User $user): static
    {
        return $this->state(fn (): array => ['assigned_to' => $user->id]);
    }
}
