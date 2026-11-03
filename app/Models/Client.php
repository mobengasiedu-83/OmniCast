<?php

namespace App\Models;

use App\Enums\Channel;
use App\Enums\ClientAccountType;
use App\Enums\ClientDocumentType;
use App\Enums\ClientService;
use App\Enums\ClientStatus;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property ClientAccountType|string $account_type
 * @property string $code
 * @property string|null $email
 * @property string|null $phone
 * @property string $timezone
 * @property ClientStatus $status
 * @property string|null $status_reason
 * @property Carbon|null $status_changed_at
 * @property Carbon|null $activated_at
 * @property Carbon|null $suspended_at
 * @property Carbon|null $archived_at
 * @property string $credit_limit
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['name', 'account_type', 'code', 'email', 'phone', 'timezone', 'status', 'status_reason', 'status_changed_at', 'activated_at', 'suspended_at', 'archived_at', 'credit_limit', 'notes', 'created_by', 'settings'])]
class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'account_type' => ClientAccountType::class,
            'status' => ClientStatus::class,
            'credit_limit' => 'decimal:2',
            'status_changed_at' => 'datetime',
            'activated_at' => 'datetime',
            'suspended_at' => 'datetime',
            'archived_at' => 'datetime',
            'settings' => 'array',
        ];
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<Contact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    /**
     * @return HasMany<ContactList, $this>
     */
    public function contactLists(): HasMany
    {
        return $this->hasMany(ContactList::class);
    }

    /**
     * @return HasMany<Campaign, $this>
     */
    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    /**
     * @return HasMany<SenderId, $this>
     */
    public function senderIds(): HasMany
    {
        return $this->hasMany(SenderId::class);
    }

    /**
     * @return HasMany<RateCard, $this>
     */
    public function rateCards(): HasMany
    {
        return $this->hasMany(RateCard::class);
    }

    /**
     * @return HasOne<Wallet, $this>
     */
    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    /**
     * The compliance documents uploaded for the tenant.
     *
     * @return HasMany<ClientDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(ClientDocument::class);
    }

    /**
     * The services the tenant is entitled to use.
     *
     * @return HasMany<ClientEntitlement, $this>
     */
    public function entitlements(): HasMany
    {
        return $this->hasMany(ClientEntitlement::class);
    }

    /**
     * The applications that led to this tenant.
     *
     * @return HasMany<ClientRegistration, $this>
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(ClientRegistration::class);
    }

    /**
     * Whether the tenant may broadcast.
     */
    public function isActive(): bool
    {
        return $this->status === ClientStatus::Active;
    }

    /**
     * Whether the tenant is still awaiting onboarding sign-off.
     */
    public function isPending(): bool
    {
        return $this->status === ClientStatus::Pending;
    }

    /**
     * Whether the tenant has been suspended.
     */
    public function isSuspended(): bool
    {
        return $this->status === ClientStatus::Suspended;
    }

    /**
     * Whether the tenant has been archived.
     */
    public function isArchived(): bool
    {
        return $this->status === ClientStatus::Archived;
    }

    /**
     * Whether the tenant may broadcast right now.
     */
    public function canBroadcast(): bool
    {
        return $this->status->canBroadcast();
    }

    /**
     * The entitlement row for a service, if one has been configured.
     */
    public function entitlementFor(ClientService $service): ?ClientEntitlement
    {
        $entitlements = $this->relationLoaded('entitlements')
            ? $this->entitlements
            : $this->entitlements()->get();

        return $entitlements->first(
            fn (ClientEntitlement $entitlement): bool => $entitlement->service === $service,
        );
    }

    /**
     * Whether the tenant may use a service.
     *
     * Entitlements are opt-out: a service without an explicit row is treated as
     * not yet configured and therefore allowed.
     */
    public function allowsService(ClientService $service): bool
    {
        $entitlement = $this->entitlementFor($service);

        return $entitlement?->is_enabled ?? true;
    }

    /**
     * Whether every service behind a campaign channel is enabled for the tenant.
     */
    public function allowsChannel(Channel $channel): bool
    {
        foreach ($channel->deliveryChannels() as $deliveryChannel) {
            $service = ClientService::fromChannel($deliveryChannel);

            if ($service !== null && ! $this->allowsService($service)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Identification document options still acceptable for verification.
     *
     * @return list<ClientDocumentType>
     */
    public function missingRequiredDocuments(): array
    {
        $verifiedTypes = $this->documents()
            ->verified()
            ->pluck('type')
            ->all();

        $verified = array_filter(array_map(
            fn (mixed $type): ?string => $type instanceof ClientDocumentType
                ? $type->value
                : (is_string($type) ? ClientDocumentType::tryFrom($type)?->value : null),
            $verifiedTypes,
        ));

        $acceptedTypeValues = array_map(
            fn (ClientDocumentType $type): string => $type->value,
            $this->accountType()->identificationTypes(),
        );

        if (array_intersect($acceptedTypeValues, $verified) !== []) {
            return [];
        }

        return $this->accountType()->identificationTypes();
    }

    /**
     * Whether at least one acceptable identification document has been verified.
     */
    public function hasVerifiedIdentification(): bool
    {
        return $this->missingRequiredDocuments() === [];
    }

    /**
     * The account type, with legacy records treated as business accounts.
     */
    public function accountType(): ClientAccountType
    {
        return $this->account_type instanceof ClientAccountType
            ? $this->account_type
            : ClientAccountType::tryFrom((string) $this->account_type) ?? ClientAccountType::Business;
    }

    /**
     * Limit the query to the client an authenticated user may see.
     */
    #[Scope]
    protected function forUser(Builder $query, User $user): void
    {
        if ($user->client_id !== null) {
            $query->whereKey($user->client_id);
        }
    }

    /**
     * Limit the query to clients that may still broadcast.
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', ClientStatus::Active->value);
    }

    /**
     * Limit the query to clients that are still awaiting activation.
     */
    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->where('status', ClientStatus::Pending->value);
    }

    /**
     * Limit the query to clients in the given lifecycle state.
     */
    #[Scope]
    protected function withStatus(Builder $query, ClientStatus $status): void
    {
        $query->where('status', $status->value);
    }
}
