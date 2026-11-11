<?php

namespace App\Models;

use App\Enums\OrganisationDpaStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An organisation's data processing agreement.
 *
 * The status belongs to the tenant rather than to a user: when the platform team
 * records the countersigned copy, every member of the account sees the same
 * answer on `settings/legal`.
 *
 * @property int $id
 * @property int $client_id
 * @property int|null $legal_document_id
 * @property int|null $legal_document_version_id
 * @property OrganisationDpaStatus $status
 * @property string|null $signatory_name
 * @property string|null $signatory_email
 * @property Carbon|null $requested_at
 * @property Carbon|null $signed_at
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'client_id', 'legal_document_id', 'legal_document_version_id', 'status',
    'signatory_name', 'signatory_email', 'requested_at', 'signed_at', 'notes',
])]
class OrganisationDpa extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrganisationDpaStatus::class,
            'requested_at' => 'datetime',
            'signed_at' => 'datetime',
        ];
    }

    /**
     * The organisation the agreement covers.
     *
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * The DPA document the agreement was built from.
     *
     * @return BelongsTo<LegalDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(LegalDocument::class, 'legal_document_id');
    }

    /**
     * The version of the DPA that was agreed.
     *
     * @return BelongsTo<LegalDocumentVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(LegalDocumentVersion::class, 'legal_document_version_id');
    }

    /**
     * Private, append-only copies of countersigned agreements.
     *
     * @return HasMany<OrganisationDpaEvidence, $this>
     */
    public function evidence(): HasMany
    {
        return $this->hasMany(OrganisationDpaEvidence::class);
    }

    /**
     * Record that the organisation asked for an agreement.
     */
    public function markRequested(?LegalDocumentVersion $version = null): void
    {
        $this->forceFill([
            'status' => OrganisationDpaStatus::Requested,
            'requested_at' => $this->requested_at ?? now(),
            'legal_document_version_id' => $version?->id ?? $this->legal_document_version_id,
        ])->save();
    }

    /**
     * Record the platform's decision and, when it is signed, the signature.
     */
    public function markSigned(?string $signatoryName, ?string $signatoryEmail, ?Carbon $signedAt = null): void
    {
        $this->forceFill([
            'status' => OrganisationDpaStatus::Signed,
            'signatory_name' => $signatoryName ?? $this->signatory_name,
            'signatory_email' => $signatoryEmail ?? $this->signatory_email,
            'signed_at' => $signedAt ?? now(),
        ])->save();
    }
}
