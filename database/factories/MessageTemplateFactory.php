<?php

namespace Database\Factories;

use App\Enums\Channel;
use App\Enums\TemplateStatus;
use App\Models\Client;
use App\Models\MessageTemplate;
use App\Models\TemplateCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MessageTemplate>
 */
class MessageTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * The default row is a platform SMS template so tests that only need a
     * starting point in the library do not have to invent a tenant first.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => null,
            'template_category_id' => null,
            'origin_template_id' => null,
            'name' => ucfirst(fake()->unique()->words(3, true)),
            'channel' => Channel::Sms,
            'language' => 'en',
            'description' => fake()->sentence(8),
            'subject' => null,
            'body' => 'Hello {{first_name}}, this is a message from {{company_name}}.',
            'status' => TemplateStatus::Active,
            'usage_count' => 0,
        ];
    }

    /**
     * Indicate that the template belongs to a client tenant.
     */
    public function forClient(Client|int $client): static
    {
        return $this->state(fn (array $attributes): array => [
            'client_id' => $client instanceof Client ? $client->id : $client,
        ]);
    }

    /**
     * Indicate that the template starts a system template's copy.
     */
    public function copiedFrom(MessageTemplate $template): static
    {
        return $this->state(fn (array $attributes): array => [
            'client_id' => $template->client_id,
            'origin_template_id' => $template->id,
            'channel' => $template->channel,
            'template_category_id' => $template->template_category_id,
        ]);
    }

    /**
     * Place the template in a category.
     */
    public function inCategory(TemplateCategory $category): static
    {
        return $this->state(fn (array $attributes): array => [
            'template_category_id' => $category->id,
        ]);
    }

    /**
     * Indicate the channel the copy is written for.
     */
    public function channel(Channel $channel): static
    {
        return $this->state(fn (array $attributes): array => match ($channel) {
            Channel::Email => [
                'channel' => Channel::Email,
                'subject' => 'Your statement is ready',
                'body' => '<p>Hello {{first_name}}, your statement is attached.</p>',
            ],
            Channel::Voice => [
                'channel' => Channel::Voice,
                'subject' => null,
                'body' => 'Hello {{first_name}}. This is a reminder from {{company_name}}.',
            ],
            default => [
                'channel' => Channel::Sms,
                'subject' => null,
                'body' => 'Hello {{first_name}}, this is a message from {{company_name}}.',
            ],
        });
    }

    /**
     * Indicate the lifecycle status of the template.
     */
    public function status(TemplateStatus $status): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => $status,
        ]);
    }

    /**
     * A tenant draft that has not been published yet.
     */
    public function draft(): static
    {
        return $this->status(TemplateStatus::Draft);
    }

    /**
     * A tenant template waiting for a platform decision.
     */
    public function pending(): static
    {
        return $this->status(TemplateStatus::Pending);
    }
}
