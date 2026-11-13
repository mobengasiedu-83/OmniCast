<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property int $client_id */
#[Fillable(['client_id', 'connector_id', 'created_by', 'reviewed_by', 'whatsapp_template_id', 'name', 'language', 'category', 'components', 'content_hash', 'review_status', 'provider_status', 'rejection_reason', 'submitted_at', 'reviewed_at'])]
class WhatsAppTemplateSubmission extends Model
{
    protected $table = 'whatsapp_template_submissions';

    protected function casts(): array
    {
        return [
            'components' => 'array',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<WhatsAppConnector, $this> */
    public function connector(): BelongsTo
    {
        return $this->belongsTo(WhatsAppConnector::class, 'connector_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return BelongsTo<WhatsAppTemplate, $this> */
    public function whatsappTemplate(): BelongsTo
    {
        return $this->belongsTo(WhatsAppTemplate::class);
    }
}
