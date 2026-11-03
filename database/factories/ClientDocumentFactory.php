<?php

namespace Database\Factories;

use App\Enums\ClientDocumentStatus;
use App\Enums\ClientDocumentType;
use App\Models\Client;
use App\Models\ClientDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientDocument>
 */
class ClientDocumentFactory extends Factory
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
            'type' => ClientDocumentType::BusinessRegistration,
            'title' => 'Business registration',
            'path' => 'client-documents/sample.pdf',
            'original_name' => 'registration.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 248_320,
            'status' => ClientDocumentStatus::Pending,
        ];
    }

    /**
     * Indicate that the document has been verified.
     */
    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ClientDocumentStatus::Verified,
            'reviewed_at' => now(),
        ]);
    }

    /**
     * Indicate that the document has been rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ClientDocumentStatus::Rejected,
            'reviewed_at' => now(),
            'rejection_reason' => 'The scan is unreadable.',
        ]);
    }
}
