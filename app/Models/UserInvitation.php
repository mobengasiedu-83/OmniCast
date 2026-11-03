<?php

namespace App\Models;

use App\Enums\InvitationStatus;
use App\Enums\Role;
use Database\Factories\UserInvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A pending request for somebody to join the platform team or a client tenant.
 *
 * @property int $id
 * @property int|null $client_id
 * @property string $email
 * @property string|null $name
 * @property string $role
 * @property string $token
 * @property InvitationStatus $status
 * @property int|null $invited_by
 * @property int|null $accepted_by
 * @property Carbon|null $expires_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $revoked_at
 * @property Carbon|null $last_sent_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'client_id', 'email', 'name', 'role', 'token', 'status', 'invited_by',
    'accepted_by', 'expires_at', 'accepted_at', 'revoked_at', 'last_sent_at',
])]
class UserInvitation extends Model
{
    /** @use HasFactory<UserInvitationFactory> */
    use HasFactory;

    /**
     * The number of days an invitation stays valid.
     */
    public const VALID_DAYS = 7;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InvitationStatus::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_sent_at' => 'datetime',
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
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    /**
     * The role the invitee will receive once they accept.
     */
    public function roleEnum(): ?Role
    {
        return Role::tryFrom($this->role);
    }

    /**
     * The human readable role label.
     */
    public function roleLabel(): string
    {
        return $this->roleEnum()?->label() ?? Str::headline($this->role);
    }

    /**
     * Whether the invitation is still waiting to be accepted.
     */
    public function isPending(): bool
    {
        return $this->status === InvitationStatus::Pending;
    }

    /**
     * Whether the invitation has passed its expiry date.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Whether the invitation link can still be used.
     */
    public function isAcceptable(): bool
    {
        return $this->isPending() && ! $this->isExpired();
    }

    /**
     * The status shown to administrators, accounting for a lapsed expiry date.
     */
    public function effectiveStatus(): InvitationStatus
    {
        if ($this->isPending() && $this->isExpired()) {
            return InvitationStatus::Expired;
        }

        return $this->status;
    }

    /**
     * Issue an invitation and return the plain-text token that belongs in the URL.
     *
     * @param  array{email: string, name?: string|null, role: Role, client_id?: int|null, invited_by?: int|null}  $attributes
     * @return array{invitation: self, token: string}
     */
    public static function issue(array $attributes): array
    {
        $token = Str::random(64);

        $invitation = self::query()->create([
            'client_id' => $attributes['client_id'] ?? null,
            'email' => Str::lower($attributes['email']),
            'name' => $attributes['name'] ?? null,
            'role' => $attributes['role']->value,
            'token' => self::hashToken($token),
            'status' => InvitationStatus::Pending,
            'invited_by' => $attributes['invited_by'] ?? null,
            'expires_at' => now()->addDays(self::VALID_DAYS),
            'last_sent_at' => now(),
        ]);

        return ['invitation' => $invitation, 'token' => $token];
    }

    /**
     * Find an invitation by the plain-text token carried in its URL.
     */
    public static function findForToken(string $token): ?self
    {
        return self::query()->where('token', self::hashToken($token))->first();
    }

    /**
     * Hash a plain-text invitation token for storage and lookup.
     */
    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Mark the invitation as accepted by the given user.
     */
    public function accept(User $user): void
    {
        $this->forceFill([
            'status' => InvitationStatus::Accepted,
            'accepted_by' => $user->id,
            'accepted_at' => now(),
        ])->save();
    }

    /**
     * Withdraw the invitation before it is accepted.
     */
    public function revoke(): void
    {
        $this->forceFill([
            'status' => InvitationStatus::Revoked,
            'revoked_at' => now(),
        ])->save();
    }

    /**
     * Record that the invitation email was sent again with a fresh token.
     */
    public function markResent(?string $hashedToken = null): void
    {
        $this->forceFill([
            'token' => $hashedToken ?? $this->token,
            'last_sent_at' => now(),
            'expires_at' => now()->addDays(self::VALID_DAYS),
        ])->save();
    }

    /**
     * Limit the query to invitations that can still be accepted.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function outstanding(Builder $query): void
    {
        $query->where('status', InvitationStatus::Pending->value)
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>', now()));
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
