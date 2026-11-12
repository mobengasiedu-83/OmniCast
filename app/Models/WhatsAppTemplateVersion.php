<?php

namespace App\Models;

use Database\Factories\WhatsAppTemplateVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['template_id', 'version', 'content_hash', 'name', 'language', 'category', 'status', 'components', 'captured_at'])]
class WhatsAppTemplateVersion extends Model
{
    protected $table = 'whatsapp_template_versions';

    /** @use HasFactory<WhatsAppTemplateVersionFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'components' => 'array',
            'captured_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    /** @return BelongsTo<WhatsAppTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(WhatsAppTemplate::class, 'template_id');
    }
}
