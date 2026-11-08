<?php

namespace Database\Factories;

use App\Enums\WebhookDeliveryStatus;
use App\Enums\WebhookEvent;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookDelivery>
 */
class WebhookDeliveryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'webhook_endpoint_id' => WebhookEndpoint::factory(),
            'client_id' => fn (array $attributes): ?int => WebhookEndpoint::query()
                ->find($attributes['webhook_endpoint_id'])?->client_id,
            'event' => WebhookEvent::CampaignSubmitted,
            'payload' => [
                'id' => 'evt_'.fake()->uuid(),
                'type' => WebhookEvent::CampaignSubmitted->value,
                'created_at' => now()->toIso8601String(),
                'data' => ['reference' => 'CMP-0001'],
            ],
            'status' => WebhookDeliveryStatus::Pending,
            'attempts' => 0,
        ];
    }

    /**
     * Indicate that the subscriber answered with a 2xx.
     */
    public function delivered(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => WebhookDeliveryStatus::Delivered,
            'attempts' => 1,
            'response_status' => 200,
            'delivered_at' => now(),
        ]);
    }

    /**
     * Indicate that the retry window ran out.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => WebhookDeliveryStatus::Failed,
            'attempts' => 5,
            'response_status' => 500,
            'error_message' => 'The subscriber answered 500.',
            'failed_at' => now(),
        ]);
    }
}
