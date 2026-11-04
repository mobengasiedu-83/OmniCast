<?php

namespace App\Models;

use App\Enums\SmsMessageEventType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $sms_message_id
 * @property SmsMessageEventType $type
 * @property string|null $provider_status
 * @property string|null $provider_event_id
 * @property string|null $error_message
 * @property Carbon|null $occurred_at
 */
#[Fillable([
    'sms_message_id', 'type', 'provider_status', 'provider_event_id',
    'error_message', 'occurred_at', 'payload',
])]
class SmsMessageEvent extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SmsMessageEventType::class,
            'occurred_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    /**
     * @return BelongsTo<SmsMessage, $this>
     */
    public function smsMessage(): BelongsTo
    {
        return $this->belongsTo(SmsMessage::class);
    }
}
