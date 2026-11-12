<?php

namespace Database\Factories;

use App\Enums\WhatsAppConnectorStatus;
use App\Models\WhatsAppConnector;
use App\Models\WhatsAppProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WhatsAppConnector>
 */
class WhatsAppConnectorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider_id' => WhatsAppProvider::factory(),
            'external_id' => Str::uuid()->toString(),
            'connector_name' => 'WhatsApp Connector',
            'waba_id' => (string) fake()->numerify('############'),
            'sender_number' => '+233200000000',
            'status' => WhatsAppConnectorStatus::Available,
            'last_synced_at' => now(),
        ];
    }
}
