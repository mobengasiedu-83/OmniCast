<?php

namespace Database\Factories;

use App\Models\SmppDelivery;
use App\Models\SmppMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SmppDelivery>
 */
class SmppDeliveryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'smpp_message_id' => SmppMessage::factory(),
            'smpp_account_id' => fn (array $attributes): ?int => SmppMessage::query()
                ->find($attributes['smpp_message_id'])?->smpp_account_id,
            'system_id' => fn (array $attributes): string => (string) SmppMessage::query()
                ->find($attributes['smpp_message_id'])?->account?->system_id,
            'stat' => 'DELIVRD',
            'err' => null,
            'text' => 'id:1 sub:001 dlvrd:001 submit date:2601081200 done date:2601081201 stat:DELIVRD err:000 text:Your statement',
        ];
    }

    /**
     * Indicate that the receipt has already been written to the session.
     */
    public function sent(): static
    {
        return $this->state(fn (array $attributes): array => ['sent_at' => now()]);
    }
}
