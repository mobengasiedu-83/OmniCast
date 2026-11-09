<?php

namespace Database\Factories;

use App\Enums\LegalDocumentStatus;
use App\Models\LegalDocument;
use App\Models\LegalDocumentVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegalDocumentVersion>
 */
class LegalDocumentVersionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'legal_document_id' => LegalDocument::factory(),
            'version' => '1.0',
            'status' => LegalDocumentStatus::Draft,
            'body' => "# Document\n\n".$this->faker->paragraphs(3, true),
            'change_summary' => $this->faker->sentence(),
            'effective_on' => null,
            'requires_reacceptance' => true,
            'published_at' => null,
            'published_by' => null,
        ];
    }

    /**
     * A version customers can already read.
     */
    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => LegalDocumentStatus::Published,
            'effective_on' => now()->toDateString(),
            'published_at' => now(),
        ]);
    }

    /**
     * A version that has been replaced by a newer one.
     */
    public function archived(): static
    {
        return $this->state(fn (): array => [
            'status' => LegalDocumentStatus::Archived,
            'effective_on' => now()->subYear()->toDateString(),
            'published_at' => now()->subYear(),
        ]);
    }
}
