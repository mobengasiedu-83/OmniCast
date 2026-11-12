<?php

namespace App\Models;

use Database\Factories\WhatsAppMediaAssetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['client_id', 'uploaded_by', 'storage_disk', 'path', 'original_name', 'mime_type', 'size_bytes', 'sha256'])]
class WhatsAppMediaAsset extends Model
{
    protected $table = 'whatsapp_media_assets';

    /** @use HasFactory<WhatsAppMediaAssetFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['size_bytes' => 'integer'];
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function fileExists(): bool
    {
        return Storage::disk($this->storage_disk)->exists($this->path);
    }
}
