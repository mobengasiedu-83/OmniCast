<?php

namespace App\Models;

use App\Enums\LoginEvent;
use Database\Factories\LoginAuditFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * An immutable record of an authentication attempt.
 *
 * @property int $id
 * @property int|null $user_id
 * @property int|null $client_id
 * @property string|null $email
 * @property LoginEvent $event
 * @property string $guard
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property array<string, mixed>|null $context
 * @property Carbon|null $created_at
 */
#[Fillable([
    'user_id', 'client_id', 'email', 'event', 'guard', 'ip_address',
    'user_agent', 'context', 'created_at',
])]
class LoginAudit extends Model
{
    /** @use HasFactory<LoginAuditFactory> */
    use HasFactory;

    /**
     * The audit trail is append only.
     */
    public const UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event' => LoginEvent::class,
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Record an authentication event against the current request.
     *
     * @param  array<string, mixed>  $context
     */
    public static function record(
        LoginEvent $event,
        Request $request,
        ?User $user = null,
        ?string $email = null,
        array $context = [],
    ): self {
        $input = $request->input('email');

        return self::query()->create([
            'user_id' => $user?->id,
            'client_id' => $user?->client_id,
            'email' => $email ?? $user->email ?? (is_string($input) ? Str::lower($input) : null),
            'event' => $event,
            'guard' => 'web',
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500),
            'context' => $context === [] ? null : $context,
            'created_at' => now(),
        ]);
    }

    /**
     * Limit the query to the client an authenticated user may see.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function forUser(Builder $query, User $user): void
    {
        if ($user->client_id !== null) {
            $query->where($query->qualifyColumn('client_id'), $user->client_id);
        }
    }
}
