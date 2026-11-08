<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\File;

/**
 * Stores an image the author dropped into an email body.
 *
 * An inline image is referenced by URL, so it has to be readable by the
 * recipient's mail client: the file lands on the public media disk that
 * `storage:link` exposes, and the editor puts the returned URL into the markup.
 */
class EmailImageController extends Controller
{
    /**
     * Store one inline image and hand its URL back to the editor.
     */
    public function store(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()?->can(Permission::ManageCampaigns->value)
            || $request->user()?->can(Permission::ManageTemplates->value),
            403,
        );

        $request->validate([
            'image' => [
                'required',
                File::image()
                    ->types(['jpg', 'jpeg', 'png', 'gif', 'webp'])
                    ->max(2 * 1024),
            ],
        ]);

        $disk = (string) config('email.images.disk', 'public');
        $clientId = $request->user()?->client_id;
        $folder = 'email-images/'.($clientId === null ? 'platform' : $clientId);
        $path = (string) $request->file('image')?->store($folder, $disk);

        return response()->json([
            'url' => Storage::disk($disk)->url($path),
            'path' => $path,
        ]);
    }
}
