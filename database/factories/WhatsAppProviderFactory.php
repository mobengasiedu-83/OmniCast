<?php

namespace Database\Factories;

use App\Enums\WhatsAppProviderStatus;
use App\Models\WhatsAppProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WhatsAppProvider>
 */
class WhatsAppProviderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Pro Plus '.Str::random(6),
            'driver' => 'pro_plus',
            'status' => WhatsAppProviderStatus::Configured,
            'is_enabled' => false,
            'connect_timeout' => 5,
            'request_timeout' => 20,
        ];
    }
}
