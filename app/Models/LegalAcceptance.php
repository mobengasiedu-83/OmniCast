<?php

namespace App\Models;

use App\Enums\LegalAcceptanceContext;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Evidence that an account accepted a specific version of a policy.
 *
 * The row is written once and never edited: the version it names is what the
 * customer agreed to, the context is the screen it happened on, and the address
 * and user agent are kept so a disputed acceptance can be reconstructed.
 *
 * @property int $id
 * @property int $legal_document_id
 * @property int $legal_document_version_id
 * @property int|null $user_id
 * @property int|null $client_id
 * @property LegalAcceptanceContext $context
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon $accepted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'legal_document_id', 'legal_document_version_id', 'user_id', 'client_id',
    'context', 'ip_address', 'user_agent', 'accepted_at',
])]
class LegalAcceptance extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'context' => LegalAcceptanceContext::class,
            'accepted_at' => 'datetime',
        ];
    }

    /**
     * The document that was accepted.
     *
     * @return BelongsTo<LegalDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(LegalDocument::class, 'legal_document_id');
    }

    /**
     * The exact wording that was accepted.
     *
     * @return BelongsTo<LegalDocumentVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(LegalDocumentVersion::class, 'legal_document_version_id');
    }

    /**
     * The account that accepted the document.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The organisation the account belongs to, when it belongs to one.
     *
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Order the newest acceptance first.
     */
    #[Scope]
    protected function latestFirst(Builder $query): void
    {
        $query->orderByDesc('accepted_at')->orderByDesc('id');
    }
}
