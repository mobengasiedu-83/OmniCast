<?php

namespace App\Models;

use App\Enums\VoiceCallEventType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $voice_call_id
 * @property VoiceCallEventType $type
 * @property string|null $digit
 * @property string|null $ivr_action
 * @property string|null $provider_event_id
 * @property int $duration_seconds
 * @property Carbon|null $occurred_at
 */
#[Fillable([
    'voice_call_id', 'type', 'digit', 'ivr_action', 'provider_event_id',
    'duration_seconds', 'occurred_at', 'payload',
])]
class VoiceCallEvent extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => VoiceCallEventType::class,
            'duration_seconds' => 'integer',
            'occurred_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    /**
     * @return BelongsTo<VoiceCall, $this>
     */
    public function voiceCall(): BelongsTo
    {
        return $this->belongsTo(VoiceCall::class);
    }
}
