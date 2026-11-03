<?php

namespace Database\Factories;

use App\Enums\CampaignContactStatus;
use App\Models\Campaign;
use App\Models\CampaignContact;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CampaignContact>
 */
class CampaignContactFactory extends Factory
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
            'contact_id' => Contact::factory(),
            'status' => CampaignContactStatus::Pending,
            'attempts' => 0,
            'cost' => 0,
        ];
    }
}
