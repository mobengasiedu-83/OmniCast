<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\EmailAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailAttachment>
 */
class EmailAttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * A one-kilobyte PDF reference is enough for a campaign that only needs an
     * attachment row; tests that read the bytes store a real file first.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'client_id' => null,
            'path' => 'email-attachments/'.fake()->uuid().'.pdf',
            'storage_disk' => 'local',
            'original_name' => 'statement.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'uploaded_by' => null,
        ];
    }
}
