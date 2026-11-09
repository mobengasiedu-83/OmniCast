<?php

namespace App\Models;

use App\Enums\SupportPriority;
use App\Enums\SupportTicketStatus;
use App\Enums\SupportTicketType;
use Database\Factories\SupportTicketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One conversation with the platform team.
 *
 * An enquiry and a ticket are the same record: a reference, a reason, a thread
 * and an owner. What differs is who opened it and how much of the platform it
 * carries — a signed-in client's ticket knows its organisation, the account and
 * optionally the campaign or wallet movement it is about, so the agent reading it
 * does not have to ask.
 *
 * @property int $id
 * @property string $reference
 * @property SupportTicketType $type
 * @property string $source
 * @property int|null $support_category_id
 * @property int|null $support_team_id
 * @property int|null $client_id
 * @property int|null $user_id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string|null $company
 * @property string $subject
 * @property string $description
 * @property SupportPriority $priority
 * @property SupportTicketStatus $status
 * @property int|null $assigned_to
 * @property int|null $campaign_id
 * @property int|null $wallet_transaction_id
 * @property Carbon|null $consent_at
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon|null $first_responded_at
 * @property Carbon|null $resolved_at
 * @property Carbon|null $closed_at
 * @property Carbon|null $last_reply_at
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read SupportCategory|null $category
 * @property-read SupportTeam|null $team
 * @property-read Client|null $client
 * @property-read User|null $user
 * @property-read User|null $assignee
 * @property-read Campaign|null $campaign
 * @property-read WalletTransaction|null $walletTransaction
 */
#[Fillable([
    'reference', 'type', 'source', 'support_category_id', 'support_team_id',
    'client_id', 'user_id', 'name', 'email', 'phone', 'company', 'subject',
    'description', 'priority', 'status', 'assigned_to', 'campaign_id',
    'wallet_transaction_id', 'consent_at', 'ip_address', 'user_agent',
    'first_responded_at', 'resolved_at', 'closed_at', 'last_reply_at', 'metadata',
])]
class SupportTicket extends Model
{
    /** @use HasFactory<SupportTicketFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SupportTicketType::class,
            'priority' => SupportPriority::class,
            'status' => SupportTicketStatus::class,
            'consent_at' => 'datetime',
            'first_responded_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'last_reply_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * The reason the conversation was opened.
     *
     * @return BelongsTo<SupportCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(SupportCategory::class, 'support_category_id');
    }

    /**
     * The team the conversation was routed to.
     *
     * @return BelongsTo<SupportTeam, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(SupportTeam::class, 'support_team_id');
    }

    /**
     * The organisation the requester belongs to, when they belong to one.
     *
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * The account that opened the conversation, when it was opened signed in.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The platform user who owns the conversation.
     *
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * The campaign the client is asking about.
     *
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * The wallet movement the client is asking about.
     *
     * @return BelongsTo<WalletTransaction, $this>
     */
    public function walletTransaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class);
    }

    /**
     * The conversation, oldest message first.
     *
     * @return HasMany<SupportTicketMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class)->orderBy('created_at')->orderBy('id');
    }

    /**
     * The files attached to the conversation.
     *
     * @return HasMany<SupportTicketAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(SupportTicketAttachment::class);
    }

    /**
     * Whether this record came from the public contact form.
     */
    public function isEnquiry(): bool
    {
        return $this->type === SupportTicketType::Enquiry;
    }

    /**
     * Whether this record is a client's trackable ticket.
     */
    public function isTicket(): bool
    {
        return $this->type === SupportTicketType::Ticket;
    }

    /**
     * Whether the conversation still needs someone's attention.
     */
    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }

    /**
     * How long ago the requester last heard from us, in a sentence.
     */
    public function waitingSince(): string
    {
        $since = $this->last_reply_at ?? $this->created_at;

        return $since === null ? 'just now' : $since->diffForHumans();
    }

    /**
     * The conversations of one type.
     */
    #[Scope]
    protected function ofType(Builder $query, SupportTicketType $type): void
    {
        $query->where('type', $type->value);
    }

    /**
     * The conversations that still need someone's attention.
     */
    #[Scope]
    protected function openConversations(Builder $query): void
    {
        $query->whereIn('status', array_map(
            fn (SupportTicketStatus $status): string => $status->value,
            array_filter(SupportTicketStatus::cases(), fn (SupportTicketStatus $status): bool => $status->isOpen()),
        ));
    }

    /**
     * Scope the register to the organisation a client user may see.
     */
    #[Scope]
    protected function forClient(Builder $query, ?int $clientId): void
    {
        $query->where('client_id', $clientId);
    }

    /**
     * Order the queue: urgent and newest first.
     */
    #[Scope]
    protected function queueOrder(Builder $query): void
    {
        $query->orderByRaw("case priority when 'urgent' then 4 when 'high' then 3 when 'normal' then 2 else 1 end desc")
            ->orderByDesc('created_at');
    }

    /**
     * Order the register newest first.
     */
    #[Scope]
    protected function latestFirst(Builder $query): void
    {
        $query->orderByDesc('created_at')->orderByDesc('id');
    }

    /**
     * Match a free-text search against the reference, subject and requester.
     */
    #[Scope]
    protected function search(Builder $query, string $term): void
    {
        $like = '%'.$term.'%';

        $query->where(fn (Builder $query) => $query
            ->where('reference', 'like', $like)
            ->orWhere('subject', 'like', $like)
            ->orWhere('description', 'like', $like)
            ->orWhere('name', 'like', $like)
            ->orWhere('email', 'like', $like)
            ->orWhere('company', 'like', $like));
    }
}
