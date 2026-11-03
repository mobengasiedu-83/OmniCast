<?php

namespace Database\Factories;

use App\Enums\Channel;
use App\Models\Campaign;
use App\Models\CampaignChannel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CampaignChannel>
 */
class CampaignChannelFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'channel' => Channel::Sms,
            'config' => null,
        ];
    }
}
