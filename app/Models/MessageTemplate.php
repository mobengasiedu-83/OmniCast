<?php

namespace App\Models;

use App\Enums\Channel;
use App\Enums\TemplateStatus;
use Database\Factories\MessageTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $client_id
 * @property int|null $template_category_id
 * @property int|null $origin_template_id
 * @property string $name
 * @property Channel $channel
 * @property string $language
 * @property string|null $description
 * @property string|null $subject
 * @property string $body
 * @property list<string>|null $variables
 * @property TemplateStatus $status
 * @property int $usage_count
 * @property int|null $created_by
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property string|null $rejection_reason
 */
#[Fillable([
    'client_id', 'template_category_id', 'origin_template_id', 'name', 'channel', 'language',
    'description', 'subject', 'body', 'variables', 'status', 'usage_count', 'created_by',
    'approved_by', 'approved_at', 'rejection_reason',
])]
class MessageTemplate extends Model
{
    /** @use HasFactory<MessageTemplateFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => Channel::class,
            'status' => TemplateStatus::class,
            'variables' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * Keep the placeholder list in step with the copy, so the editor can show
     * the fields a template needs before it is ever used in a campaign.
     */
    protected static function booted(): void
    {
        static::saving(function (MessageTemplate $template): void {
            $template->variables = $template->detectVariables();
        });
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<TemplateCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(TemplateCategory::class, 'template_category_id');
    }

    /**
     * The library template a tenant copy was taken from.
     *
     * @return BelongsTo<MessageTemplate, $this>
     */
    public function origin(): BelongsTo
    {
        return $this->belongsTo(self::class, 'origin_template_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function favouritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'template_favourites')->withTimestamps();
    }

    /**
     * @return HasMany<Campaign, $this>
     */
    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class, 'message_template_id');
    }

    /**
     * Whether this is a platform template rather than a tenant one.
     */
    public function isSystem(): bool
    {
        return $this->client_id === null;
    }

    /**
     * Whether the user may rewrite this template's copy.
     */
    public function isEditableBy(User $user): bool
    {
        if (! $this->status->canBeEdited()) {
            return false;
        }

        return $this->isSystem()
            ? $user->client_id === null
            : $user->client_id === null || $user->client_id === $this->client_id;
    }

    /**
     * The `{{tag}}` placeholders the copy expects, in the order they appear.
     *
     * @return list<string>
     */
    public function detectVariables(): array
    {
        $copy = trim(($this->subject ?? '').' '.$this->body);

        preg_match_all('/\{\{\s*([A-Za-z0-9_.-]+)\s*\}\}/', $copy, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }

    /**
     * The placeholders as the editor writes them, ready to be shown as chips.
     *
     * @return list<string>
     */
    public function variableTags(): array
    {
        return array_map(fn (string $tag): string => '{{'.$tag.'}}', $this->variables ?? []);
    }

    /**
     * Whether the account may see this template in its library.
     */
    #[Scope]
    protected function visibleTo(Builder $query, ?int $clientId): void
    {
        $query->where(function (Builder $query) use ($clientId): void {
            $query->whereNull($query->qualifyColumn('client_id'));

            if ($clientId !== null) {
                $query->orWhere($query->qualifyColumn('client_id'), $clientId);
            }
        });
    }

    /**
     * Limit the query to one delivery channel.
     */
    #[Scope]
    protected function forChannel(Builder $query, Channel $channel): void
    {
        $query->where($query->qualifyColumn('channel'), $channel->value);
    }

    /**
     * Limit the query to templates that are offered in the library.
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where($query->qualifyColumn('status'), TemplateStatus::Active->value);
    }
}
