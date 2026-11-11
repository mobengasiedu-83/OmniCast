<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only metadata for a private copy of a countersigned DPA.
 *
 * @property int $id
 * @property int $organisation_dpa_id
 * @property string $storage_disk
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size_bytes
 * @property string $sha256
 * @property int|null $uploaded_by
 * @property Carbon|null $created_at
 */
#[Fillable([
    'organisation_dpa_id', 'storage_disk', 'path', 'original_name', 'mime_type',
    'size_bytes', 'sha256', 'uploaded_by',
])]
class OrganisationDpaEvidence extends Model
{
    /**
     * The DPA record this evidence supports.
     *
     * @return BelongsTo<OrganisationDpa, $this>
     */
    public function organisationDpa(): BelongsTo
    {
        return $this->belongsTo(OrganisationDpa::class);
    }

    /**
     * The platform administrator who uploaded the document.
     *
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
