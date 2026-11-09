<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One account's copy of a policy announcement.
 *
 * The row records the inbox notification that carried the news, so the admin
 * screens can report how many customers were told about a version and how many
 * have since acknowledged it.
 *
 * @property int $id
 * @property int $legal_document_id
 * @property int $legal_document_version_id
 * @property int|null $user_id
 * @property int|null $client_id
 * @property string|null $notification_id
 * @property Carbon|null $sent_at
 * @property Carbon|null $acknowledged_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'legal_document_id', 'legal_document_version_id', 'user_id', 'client_id',
    'notification_id', 'sent_at', 'acknowledged_at',
])]
class LegalNotification extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['sent_at' => 'datetime', 'acknowledged_at' => 'datetime'];
    }

    /**
     * The document that changed.
     *
     * @return BelongsTo<LegalDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(LegalDocument::class, 'legal_document_id');
    }

    /**
     * The version the announcement described.
     *
     * @return BelongsTo<LegalDocumentVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(LegalDocumentVersion::class, 'legal_document_version_id');
    }

    /**
     * The account the announcement was addressed to.
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
}
