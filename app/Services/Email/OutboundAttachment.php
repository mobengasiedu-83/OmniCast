<?php

namespace App\Services\Email;

use Illuminate\Support\Facades\Storage;

/**
 * One file the dispatcher asks the transport to deliver with an email.
 *
 * The transport is handed a reference to the stored bytes rather than the bytes
 * themselves: an SMTP submission reads the file when it builds the message, and
 * a JSON gateway base64-encodes it at the moment of the request. Either way the
 * queued job never carries the file.
 */
final class OutboundAttachment
{
    public function __construct(
        public readonly string $disk,
        public readonly string $path,
        public readonly string $name,
        public readonly string $mimeType,
    ) {}

    /**
     * Whether the bytes are still where the row says they are.
     */
    public function exists(): bool
    {
        return Storage::disk($this->disk)->exists($this->path);
    }

    /**
     * The stored bytes, or null when the file has been removed since upload.
     */
    public function contents(): ?string
    {
        return $this->exists() ? Storage::disk($this->disk)->get($this->path) : null;
    }

    /**
     * The size in bytes, so a transport can refuse a message over its limit.
     */
    public function size(): int
    {
        return $this->exists() ? (int) Storage::disk($this->disk)->size($this->path) : 0;
    }
}
