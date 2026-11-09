<?php

namespace App\Models;

use Database\Factories\SupportTicketAttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * One file a requester or an agent attached to a conversation.
 *
 * The bytes stay on a private disk; only the reference is stored, and a download
 * always goes through the ticket's policy, so a file is never reachable from a
 * public URL.
 *
 * @property int $id
 * @property int $support_ticket_id
 * @property int|null $support_ticket_message_id
 * @property string $path
 * @property string $storage_disk
 * @property string $original_name
 * @property string $mime_type
 * @property int $size_bytes
 * @property int|null $uploaded_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read SupportTicket $ticket
 * @property-read SupportTicketMessage|null $message
 */
#[Fillable([
    'support_ticket_id', 'support_ticket_message_id', 'path', 'storage_disk',
    'original_name', 'mime_type', 'size_bytes', 'uploaded_by',
])]
class SupportTicketAttachment extends Model
{
    /** @use HasFactory<SupportTicketAttachmentFactory> */
    use HasFactory;

    /**
     * The conversation the file belongs to.
     *
     * @return BelongsTo<SupportTicket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    /**
     * The message the file came with.
     *
     * @return BelongsTo<SupportTicketMessage, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(SupportTicketMessage::class, 'support_ticket_message_id');
    }

    /**
     * The account that uploaded the file.
     *
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * The size as a person reads it, for the attachment list.
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
}
