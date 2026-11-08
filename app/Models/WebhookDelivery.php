<?php

namespace App\Models;

use App\Enums\WebhookDeliveryStatus;
use App\Enums\WebhookEvent;
use Carbon\CarbonInterface;
use Database\Factories\WebhookDeliveryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One callback OmniCast owes (or has delivered to) a subscriber.
 *
 * `payload` is frozen at the moment the event happened, so a replay sends the
 * same body even after the campaign moved on.
 *
 * @property int $id
 * @property int $webhook_endpoint_id
 * @property int|null $client_id
 * @property WebhookEvent $event
 * @property array<string, mixed> $payload
 * @property WebhookDeliveryStatus $status
 * @property int $attempts
 * @property int|null $response_status
 * @property string|null $response_body
 * @property string|null $error_message
 * @property Carbon|null $next_attempt_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $failed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'webhook_endpoint_id', 'client_id', 'event', 'payload', 'status', 'attempts',
    'response_status', 'response_body', 'error_message', 'next_attempt_at',
    'delivered_at', 'failed_at',
])]
class WebhookDelivery extends Model
{
    /** @use HasFactory<WebhookDeliveryFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event' => WebhookEvent::class,
            'payload' => 'array',
            'status' => WebhookDeliveryStatus::class,
            'attempts' => 'integer',
            'response_status' => 'integer',
            'next_attempt_at' => 'datetime',
            'delivered_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<WebhookEndpoint, $this>
     */
    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Limit the query to deliveries that have not reached a final state.
     */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereIn('status', [
            WebhookDeliveryStatus::Pending->value,
            WebhookDeliveryStatus::Delivering->value,
        ]);
    }

    /**
     * Limit the query to one tenant's deliveries.
     */
    #[Scope]
    protected function forClient(Builder $query, ?int $clientId): void
    {
        $clientId === null
            ? $query->whereNull('client_id')
            : $query->where('client_id', $clientId);
    }

    /**
     * Record a successful attempt.
     */
    public function markDelivered(int $status, string $body): void
    {
        $this->forceFill([
            'status' => WebhookDeliveryStatus::Delivered,
            'response_status' => $status,
            'response_body' => mb_substr($body, 0, (int) config('webhooks.delivery.max_response_bytes', 4096)),
            'error_message' => null,
            'next_attempt_at' => null,
            'delivered_at' => now(),
        ])->save();
    }

    /**
     * Record a failed attempt and the moment the retry is due.
     */
    public function markFailed(?int $status, ?string $body, string $error, bool $final, ?CarbonInterface $retryAt = null): void
    {
        $this->forceFill([
            'status' => $final ? WebhookDeliveryStatus::Failed : WebhookDeliveryStatus::Pending,
            'response_status' => $status,
            'response_body' => $body === null
                ? null
                : mb_substr($body, 0, (int) config('webhooks.delivery.max_response_bytes', 4096)),
            'error_message' => mb_substr($error, 0, 250),
            'failed_at' => $final ? now() : null,
            'next_attempt_at' => $final ? null : $retryAt,
        ])->save();
    }
}
