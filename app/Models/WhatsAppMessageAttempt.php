<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['recipient_id', 'attempt_number', 'correlation_id', 'status', 'http_status', 'provider_reference', 'error_category', 'error_message', 'latency_ms', 'request_payload_redacted', 'response_payload_redacted', 'started_at', 'finished_at'])]
class WhatsAppMessageAttempt extends Model
{
    protected $table = 'whatsapp_message_attempts';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['request_payload_redacted' => 'array', 'response_payload_redacted' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime', 'attempt_number' => 'integer', 'latency_ms' => 'integer'];
    }

    /** @return BelongsTo<WhatsAppCampaignRecipient, $this> */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(WhatsAppCampaignRecipient::class, 'recipient_id');
    }
}
