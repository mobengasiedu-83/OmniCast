<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\WhatsAppMediaAsset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsAppMediaAsset>
 */
class WhatsAppMediaAssetFactory extends Factory
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
            'storage_disk' => 'local',
            'path' => 'whatsapp-media/test/image.jpg',
            'original_name' => 'image.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1024,
            'sha256' => hash('sha256', fake()->uuid()),
        ];
    }
}
