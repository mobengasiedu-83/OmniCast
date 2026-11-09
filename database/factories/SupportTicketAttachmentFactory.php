<?php

namespace Database\Factories;

use App\Models\SupportTicket;
use App\Models\SupportTicketAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportTicketAttachment>
 */
class SupportTicketAttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'support_ticket_id' => SupportTicket::factory(),
            'support_ticket_message_id' => null,
            'path' => 'support/'.$this->faker->uuid().'.png',
            'storage_disk' => 'local',
            'original_name' => 'screenshot.png',
            'mime_type' => 'image/png',
            'size_bytes' => $this->faker->numberBetween(2048, 512000),
            'uploaded_by' => null,
        ];
    }
}
