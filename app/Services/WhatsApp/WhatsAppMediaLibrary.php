<?php

namespace App\Services\WhatsApp;

use App\Models\User;
use App\Models\WhatsAppMediaAsset;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class WhatsAppMediaLibrary
{
    public function store(User $user, UploadedFile $file): WhatsAppMediaAsset
    {
        $disk = (string) config('whatsapp.media.disk', 'local');
        $path = $file->store('whatsapp/'.($user->client_id ?? 'platform'), $disk);

        return WhatsAppMediaAsset::query()->create([
            'client_id' => $user->client_id,
            'uploaded_by' => $user->id,
            'storage_disk' => $disk,
            'path' => $path,
            'original_name' => Str::limit($file->getClientOriginalName(), 255, ''),
            'mime_type' => (string) $file->getMimeType(),
            'size_bytes' => (int) $file->getSize(),
            'sha256' => hash_file('sha256', $file->getRealPath()) ?: hash('sha256', $path),
        ]);
    }

    public function temporaryHttpsUrl(WhatsAppMediaAsset $asset): string
    {
        URL::forceRootUrl((string) config('app.url'));
        URL::forceScheme('https');
        try {
            $url = URL::temporarySignedRoute('whatsapp.media.show', now()->addMinutes(30), ['asset' => $asset->id], absolute: true);
        } finally {
            URL::forceScheme(null);
            URL::forceRootUrl(null);
        }

        return $url;
    }

    public function stream(WhatsAppMediaAsset $asset): mixed
    {
        return Storage::disk($asset->storage_disk)->response($asset->path, null, [
            'Content-Type' => $asset->mime_type,
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
