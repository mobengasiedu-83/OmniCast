<?php

namespace App\Models;

use App\Services\Email\OutboundAttachment;
use Database\Factories\EmailAttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * One file an email campaign delivers with its body.
 *
 * The bytes stay on the disk named in `storage_disk`; only the reference to them
 * is stored here, so a queued dispatch reads the file when the transport submits
 * the message and the job payload stays small.
 *
 * @property int $id
 * @property int $campaign_id
 * @property int|null $client_id
 * @property string $path
 * @property string $storage_disk
 * @property string $original_name
 * @property string $mime_type
 * @property int $size_bytes
 * @property int|null $uploaded_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Campaign $campaign
 * @property-read User|null $uploader
 */
#[Fillable([
    'campaign_id', 'client_id', 'path', 'storage_disk', 'original_name', 'mime_type',
    'size_bytes', 'uploaded_by',
])]
class EmailAttachment extends Model
{
    /** @use HasFactory<EmailAttachmentFactory> */
    use HasFactory;

    /**
     * The campaign the file is delivered with.
     *
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * The tenant that owns the campaign the file belongs to.
     *
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * The user who added the file.
     *
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Whether the file is an image a mail client can show inline.
     */
    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * The size as a person reads it, for the upload list.
     */
    public function humanSize(): string
    {
        $kilobytes = $this->size_bytes / 1024;

        if ($kilobytes < 1024) {
            return max(1, (int) round($kilobytes)).' KB';
        }

        return round($kilobytes / 1024, 1).' MB';
    }

    /**
     * Whether the stored bytes can still be read.
     */
    public function exists(): bool
    {
        return Storage::disk($this->storage_disk)->exists($this->path);
    }

    /**
     * The file as the mail transport needs it.
     */
    public function toOutbound(): OutboundAttachment
    {
        return new OutboundAttachment(
            disk: $this->storage_disk,
            path: $this->path,
            name: $this->original_name,
            mimeType: $this->mime_type,
        );
    }
}
