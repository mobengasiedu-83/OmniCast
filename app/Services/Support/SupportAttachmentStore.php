<?php

namespace App\Services\Support;

use App\Models\SupportTicket;
use App\Models\SupportTicketAttachment;
use App\Models\SupportTicketMessage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Stores the files attached to a support conversation.
 *
 * The limit is enforced by the request that accepts the upload; this service
 * only writes what it is given, on the private disk named in `config/support.php`,
 * and records the reference. Nothing is ever published: a download goes through
 * the ticket's policy.
 */
class SupportAttachmentStore
{
    /**
     * Store each uploaded file against the message it came with.
     *
     * @param  array<int, UploadedFile|null>  $files
     */
    public function store(
        SupportTicket $ticket,
        ?SupportTicketMessage $message,
        array $files,
        ?User $uploader,
    ): void {
        $disk = $this->disk();
        $folder = 'support/'.$ticket->id;

        foreach (array_filter($files) as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $path = $file->store($folder, $disk);

            if (! is_string($path)) {
                continue;
            }

            SupportTicketAttachment::query()->create([
                'support_ticket_id' => $ticket->id,
                'support_ticket_message_id' => $message?->id,
                'path' => $path,
                'storage_disk' => $disk,
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'mime_type' => (string) ($file->getClientMimeType() ?: $file->getMimeType()),
                'size_bytes' => (int) $file->getSize(),
                'uploaded_by' => $uploader?->id,
            ]);
        }
    }

    /**
     * The private disk the files are stored on.
     */
    public function disk(): string
    {
        $disk = (string) config('support.attachments.disk', 'local');

        return $disk === '' ? 'local' : $disk;
    }

    /**
     * Whether a stored file can still be streamed.
     */
    public function exists(SupportTicketAttachment $attachment): bool
    {
        return Storage::disk($attachment->storage_disk)->exists($attachment->path);
    }
}
