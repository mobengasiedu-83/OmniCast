<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property int|null $client_id
 * @property string $name
 * @property string $email
 * @property string|null $job_title
 * @property bool $is_active
 * @property Carbon|null $suspended_at
 * @property string|null $suspension_reason
 * @property Carbon|null $last_login_at
 * @property string|null $last_login_ip
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'client_id', 'job_title', 'is_active'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
            'suspended_at' => 'datetime',
            'last_login_at' => 'datetime',
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
     * The invitations this user has sent.
     *
     * @return HasMany<UserInvitation, $this>
     */
    public function invitationsSent(): HasMany
    {
        return $this->hasMany(UserInvitation::class, 'invited_by');
    }

    /**
     * The authentication audit entries recorded for this user.
     *
     * @return HasMany<LoginAudit, $this>
     */
    public function loginAudits(): HasMany
    {
        return $this->hasMany(LoginAudit::class);
    }

    /**
     * Whether the user belongs to the platform team rather than a client tenant.
     */
    public function isPlatformUser(): bool
    {
        return $this->client_id === null;
    }

    /**
     * Determine whether the user holds the given role.
     */
    public function hasRoleNamed(Role $role): bool
    {
        return $this->hasRole($role->value);
    }

    /**
     * The first platform role held by the user, if any.
     */
    public function primaryRole(): ?Role
    {
        $name = $this->getRoleNames()->first();

        return is_string($name) ? Role::tryFrom($name) : null;
    }

    /**
     * The human readable label of the user's first role.
     */
    public function primaryRoleLabel(): ?string
    {
        return $this->primaryRole()?->label();
    }

    /**
     * Whether the account may sign in and act on the platform.
     */
    public function isActive(): bool
    {
        return $this->is_active && $this->suspended_at === null;
    }

    /**
     * Whether the account has been suspended by an administrator.
     */
    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    /**
     * Suspend the account, blocking sign-in until it is reactivated.
     */
    public function suspend(string $reason): void
    {
        $this->forceFill([
            'is_active' => false,
            'suspended_at' => now(),
            'suspension_reason' => $reason,
        ])->save();
    }

    /**
     * Reactivate a suspended account.
     */
    public function reactivate(): void
    {
        $this->forceFill([
            'is_active' => true,
            'suspended_at' => null,
            'suspension_reason' => null,
        ])->save();
    }

    /**
     * Record the timestamp and address of the last successful sign-in.
     */
    public function recordLogin(string $ipAddress): void
    {
        $this->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $ipAddress,
        ])->saveQuietly();
    }

    /**
     * Whether the user holds the super admin role.
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRoleNamed(Role::SuperAdmin);
    }
}
