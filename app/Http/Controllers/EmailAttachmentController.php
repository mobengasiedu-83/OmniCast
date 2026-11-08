<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\EmailAttachment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Hands a campaign attachment to a reviewer or operator.
 *
 * The bytes sit on a private disk, so a download always goes through the
 * campaign's view policy: a tenant never reads another tenant's file, and a
 * recipient never receives a public link.
 */
class EmailAttachmentController extends Controller
{
    /**
     * Download one file attached to the campaign.
     */
    public function download(Campaign $campaign, EmailAttachment $attachment): StreamedResponse
    {
        Gate::authorize('view', $campaign);

        abort_unless($attachment->campaign_id === $campaign->id, 404);

        $disk = Storage::disk($attachment->storage_disk);

        abort_unless($disk->exists($attachment->path), 404);

        return $disk->download($attachment->path, $attachment->original_name);
    }
}
