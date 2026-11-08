<?php

namespace Database\Factories;

use App\Enums\WebhookEvent;
use App\Models\Client;
use App\Models\WebhookEndpoint;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WebhookEndpoint>
 */
class WebhookEndpointFactory extends Factory
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
            'name' => fake()->words(2, true),
            'url' => 'https://'.fake()->domainName().'/omnicast/webhooks',
            'secret' => 'whsec_'.Str::random(32),
            'events' => WebhookEvent::values(),
            'description' => null,
            'api_version' => 'v1',
            'is_active' => true,
            'consecutive_failures' => 0,
        ];
    }

    /**
     * Indicate that the endpoint covers every client rather than one tenant.
     */
    public function platform(): static
    {
        return $this->state(fn (array $attributes): array => ['client_id' => null]);
    }

    /**
     * Indicate that the endpoint subscribes to a subset of the catalogue.
     *
     * @param  list<WebhookEvent>  $events
     */
    public function subscribingTo(array $events): static
    {
        return $this->state(fn (array $attributes): array => [
            'events' => array_map(fn (WebhookEvent $event): string => $event->value, $events),
        ]);
    }

    /**
     * Indicate that the endpoint has been switched off.
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
            'disabled_at' => now(),
        ]);
    }
}
