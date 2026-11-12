<?php

namespace Database\Factories;

use App\Models\WhatsAppTemplate;
use App\Models\WhatsAppTemplateVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsAppTemplateVersion>
 */
class WhatsAppTemplateVersionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'template_id' => WhatsAppTemplate::factory(),
            'version' => 1,
            'content_hash' => hash('sha256', fake()->uuid()),
            'name' => 'welcome_offer',
            'language' => 'en',
            'category' => 'MARKETING',
            'status' => 'approved',
            'components' => [['type' => 'BODY', 'text' => 'Hello {{1}}']],
            'captured_at' => now(),
        ];
    }
}
