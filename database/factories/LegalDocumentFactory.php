<?php

namespace Database\Factories;

use App\Enums\LegalDocumentType;
use App\Models\LegalDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegalDocument>
 */
class LegalDocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => $this->faker->unique()->slug(2),
            'title' => $this->faker->sentence(3),
            'summary' => $this->faker->sentence(),
            'sort_order' => $this->faker->numberBetween(0, 10),
            'is_active' => true,
            'requires_acceptance_at_registration' => false,
            'requires_acceptance_on_campaign_submission' => false,
            'requires_acceptance_on_wallet_top_up' => false,
        ];
    }

    /**
     * One of the five documents the platform ships with.
     */
    public function type(LegalDocumentType $type): static
    {
        return $this->state(fn (): array => [
            'slug' => $type->value,
            'title' => $type->label(),
            'summary' => $type->summary(),
            'requires_acceptance_at_registration' => $type->isAcceptedAtRegistration(),
            'requires_acceptance_on_campaign_submission' => $type->isAcceptedOnCampaignSubmission(),
            'requires_acceptance_on_wallet_top_up' => $type->isAcceptedOnWalletTopUp(),
        ]);
    }
}
