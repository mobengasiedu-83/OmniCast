<?php

namespace App\Models;

use App\Enums\ClientDocumentStatus;
use App\Enums\ClientDocumentType;
use Database\Factories\ClientDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A compliance document uploaded for a client tenant.
 *
 * Files live on the private disk; only the row is exposed to the front end.
 *
 * @property int $id
 * @property int $client_id
 * @property ClientDocumentType $type
 * @property string $title
 * @property string $path
 * @property string|null $original_name
 * @property string|null $mime_type
 * @property int|null $size_bytes
 * @property ClientDocumentStatus $status
 * @property int|null $uploaded_by
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $rejection_reason
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'client_id', 'type', 'title', 'path', 'original_name', 'mime_type',
    'size_bytes', 'status', 'uploaded_by', 'reviewed_by', 'reviewed_at',
    'rejection_reason', 'expires_at',
])]
class ClientDocument extends Model
{
    /** @use HasFactory<ClientDocumentFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ClientDocumentType::class,
            'status' => ClientDocumentStatus::class,
            'size_bytes' => 'integer',
            'reviewed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * The tenant the document belongs to.
     *
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * The user who uploaded the document.
     *
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * The platform user who verified or rejected the document.
     *
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Whether the document has been accepted by a reviewer.
     */
    public function isVerified(): bool
    {
        return $this->status->isVerified();
    }

    /**
     * Whether the document is still waiting on a reviewer.
     */
    public function isPending(): bool
    {
        return $this->status === ClientDocumentStatus::Pending;
    }

    /**
     * The file size in a human readable unit.
     */
    public function displaySize(): string
    {
        $bytes = $this->size_bytes ?? 0;

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1).' MB';
        }

        return max(1, (int) round($bytes / 1024)).' KB';
    }

    /**
     * Limit the query to the documents of one tenant.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function forClient(Builder $query, Client $client): void
    {
        $query->where($query->qualifyColumn('client_id'), $client->id);
    }

    /**
     * Limit the query to verified documents.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function verified(Builder $query): void
    {
        $query->where('status', ClientDocumentStatus::Verified->value);
    }

    /**
     * Limit the query to documents still awaiting a reviewer.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function awaitingReview(Builder $query): void
    {
        $query->where('status', ClientDocumentStatus::Pending->value);
    }
}
