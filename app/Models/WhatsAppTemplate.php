<?php

namespace App\Models;

use App\Enums\WhatsAppTemplateStatus;
use Database\Factories\WhatsAppTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['connector_id', 'meta_template_id', 'name', 'language', 'category', 'status', 'components', 'content_hash', 'last_synced_at'])]
class WhatsAppTemplate extends Model
{
    protected $table = 'whatsapp_templates';

    /** @use HasFactory<WhatsAppTemplateFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => WhatsAppTemplateStatus::class,
            'components' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<WhatsAppConnector, $this> */
    public function connector(): BelongsTo
    {
        return $this->belongsTo(WhatsAppConnector::class, 'connector_id');
    }

    /** @return HasMany<WhatsAppTemplateVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(WhatsAppTemplateVersion::class, 'template_id')->orderByDesc('version');
    }

    /** @return HasMany<WhatsAppTemplateSubmission, $this> */
    public function submissions(): HasMany
    {
        return $this->hasMany(WhatsAppTemplateSubmission::class, 'whatsapp_template_id');
    }

    public function isApproved(): bool
    {
        return $this->status === WhatsAppTemplateStatus::Approved;
    }
}
