<?php

namespace App\Models;

use Database\Factories\SupportTicketMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One message in a support conversation.
 *
 * The requester's words, the agent's reply and the internal note an agent leaves
 * for a colleague live in the same table, because they are the same timeline. A
 * note is marked internal and is filtered out before the requester's screen is
 * rendered — it is never sent by email either.
 *
 * @property int $id
 * @property int $support_ticket_id
 * @property int|null $user_id
 * @property string $author_name
 * @property string|null $author_email
 * @property string $body
 * @property bool $is_internal
 * @property string $source
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read SupportTicket $ticket
 */
#[Fillable([
    'support_ticket_id', 'user_id', 'author_name', 'author_email', 'body',
    'is_internal', 'source',
])]
class SupportTicketMessage extends Model
{
    /** @use HasFactory<SupportTicketMessageFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
        ];
    }

    /**
     * The conversation the message belongs to.
     *
     * @return BelongsTo<SupportTicket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    /**
     * The account that wrote the message, when it was written signed in.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The files that came with the message.
     *
     * @return HasMany<SupportTicketAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(SupportTicketAttachment::class);
    }

    /**
     * Whether the message came from the platform team.
     */
    public function isFromAgent(): bool
    {
        return $this->source === 'agent';
    }

    /**
     * The messages a requester may read: internal notes are removed.
     */
    #[Scope]
    protected function visibleToRequester(Builder $query): void
    {
        $query->where('is_internal', false);
    }
}
