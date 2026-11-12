<?php

namespace Database\Factories;

use App\Enums\WhatsAppTemplateStatus;
use App\Models\WhatsAppConnector;
use App\Models\WhatsAppTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WhatsAppTemplate>
 */
class WhatsAppTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'connector_id' => WhatsAppConnector::factory(),
            'meta_template_id' => (string) fake()->unique()->numerify('############'),
            'name' => Str::snake(fake()->words(3, true)),
            'language' => 'en',
            'category' => 'MARKETING',
            'status' => WhatsAppTemplateStatus::Approved,
            'components' => [['type' => 'BODY', 'text' => 'Hello {{1}}']],
            'content_hash' => hash('sha256', fake()->uuid()),
            'last_synced_at' => now(),
        ];
    }
}
