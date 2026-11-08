<?php

namespace App\Models;

use App\Enums\WebhookEvent;
use Database\Factories\WebhookEndpointFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One URL a tenant or the platform wants event callbacks on.
 *
 * The row owns the signing secret and the event subscription; the deliveries it
 * produced live in `webhook_deliveries` so a failed callback stays auditable
 * after the endpoint has been fixed.
 *
 * @property int $id
 * @property int|null $client_id
 * @property string $name
 * @property string $url
 * @property string $secret
 * @property list<string> $events
 * @property string|null $description
 * @property string $api_version
 * @property bool $is_active
 * @property int $consecutive_failures
 * @property Carbon|null $disabled_at
 * @property Carbon|null $last_success_at
 * @property Carbon|null $last_failure_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'client_id', 'name', 'url', 'secret', 'events', 'description', 'api_version',
    'is_active', 'consecutive_failures', 'disabled_at', 'last_success_at',
    'last_failure_at', 'created_by',
])]
class WebhookEndpoint extends Model
{
    /** @use HasFactory<WebhookEndpointFactory> */
    use HasFactory;

    /**
     * The secret is never serialised onto a screen or an API response.
     *
     * @var list<string>
     */
    protected $hidden = ['secret'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'events' => 'array',
            'is_active' => 'boolean',
            'consecutive_failures' => 'integer',
            'disabled_at' => 'datetime',
            'last_success_at' => 'datetime',
            'last_failure_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<WebhookDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    /**
     * Limit the query to endpoints that would receive the given event.
     */
    #[Scope]
    protected function subscribingTo(Builder $query, WebhookEvent $event): void
    {
        $query->where('is_active', true)
            ->whereNull('disabled_at')
            ->whereJsonContains('events', $event->value);
    }

    /**
     * Limit the query to one tenant's endpoints (or the platform's when null).
     */
    #[Scope]
    protected function forClient(Builder $query, ?int $clientId): void
    {
        $clientId === null
            ? $query->whereNull('client_id')
            : $query->where('client_id', $clientId);
    }

    /**
     * Whether the endpoint is currently receiving events.
     */
    public function isDeliverable(): bool
    {
        return $this->is_active && $this->disabled_at === null;
    }

    /**
     * Whether the endpoint is subscribed to an event.
     */
    public function subscribesTo(WebhookEvent $event): bool
    {
        return in_array($event->value, $this->events ?? [], true);
    }

    /**
     * A masked secret for the screens: enough to identify it, not to use it.
     */
    public function maskedSecret(): string
    {
        $secret = (string) $this->secret;

        return strlen($secret) <= 12
            ? str_repeat('•', 12)
            : substr($secret, 0, 11).str_repeat('•', 12);
    }
}
