<?php

namespace App\Models;

use App\Enums\EmailMessageEventType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $email_message_id
 * @property EmailMessageEventType $type
 * @property string|null $provider_status
 * @property string|null $provider_event_id
 * @property string|null $error_message
 * @property Carbon|null $occurred_at
 */
#[Fillable([
    'email_message_id', 'type', 'provider_status', 'provider_event_id',
    'error_message', 'occurred_at', 'payload',
])]
class EmailMessageEvent extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => EmailMessageEventType::class,
            'occurred_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    /**
     * @return BelongsTo<EmailMessage, $this>
     */
    public function emailMessage(): BelongsTo
    {
        return $this->belongsTo(EmailMessage::class);
    }
}
