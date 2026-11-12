<?php

namespace Database\Factories;

use App\Enums\WhatsAppApprovalStatus;
use App\Models\Client;
use App\Models\WhatsAppClientConnector;
use App\Models\WhatsAppConnector;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsAppClientConnector>
 */
class WhatsAppClientConnectorFactory extends Factory
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
            'connector_id' => WhatsAppConnector::factory(),
            'enabled' => false,
            'approval_status' => WhatsAppApprovalStatus::Pending,
        ];
    }
}
