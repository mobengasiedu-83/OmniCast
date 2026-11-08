<?php

namespace App\Services\Email;

use App\Models\Campaign;
use App\Models\EmailAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Keeps the files attached to an email campaign in step with the editor.
 *
 * A file removed in the editor is deleted from its disk as well as the table, so
 * a cancelled draft never leaves bytes behind that nothing points at. New files
 * are stored on the configured attachment disk and only their reference is kept
 * on the campaign.
 */
class EmailAttachmentStore
{
    /**
     * How many files one campaign may deliver.
     */
    public const MAX_FILES = 5;

    /**
     * The largest single file, in kilobytes.
     */
    public const MAX_FILE_KILOBYTES = 5120;

    /**
     * The largest message a campaign may build, in kilobytes.
     */
    public const MAX_TOTAL_KILOBYTES = 10240;

    /**
     * Delete the files the author removed, then store the ones they added.
     *
     * @param  array<int, UploadedFile|null>  $files
     * @param  list<int>  $removeIds
     */
    public function sync(Campaign $campaign, array $files, array $removeIds, ?User $user): void
    {
        if ($removeIds !== []) {
            $this->forget($campaign, $removeIds);
        }

        $disk = $this->disk();
        $folder = 'email-attachments/'.($campaign->client_id === null ? 'platform' : $campaign->client_id);

        foreach (array_filter($files) as $file) {
            $path = $file->store($folder, $disk);

            if (! is_string($path)) {
                continue;
            }

            $campaign->emailAttachments()->create([
                'client_id' => $campaign->client_id,
                'path' => $path,
                'storage_disk' => $disk,
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'mime_type' => (string) ($file->getClientMimeType() ?: $file->getMimeType()),
                'size_bytes' => (int) $file->getSize(),
                'uploaded_by' => $user?->id,
            ]);
        }
    }

    /**
     * Remove the named attachments, and their bytes, from the campaign.
     *
     * @param  list<int>  $ids
     */
    public function forget(Campaign $campaign, array $ids): void
    {
        $campaign->emailAttachments()
            ->whereIn('id', $ids)
            ->get()
            ->each(function (EmailAttachment $attachment): void {
                Storage::disk($attachment->storage_disk)->delete($attachment->path);
                $attachment->delete();
            });
    }

    /**
     * The disk the files are stored on. It stays private: only the transport
     * reads it, when the message is submitted.
     */
    public function disk(): string
    {
        $disk = (string) config('email.attachments.disk', 'local');

        return $disk === '' ? 'local' : $disk;
    }
}
