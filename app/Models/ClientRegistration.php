<?php

namespace App\Models;

use App\Enums\ClientAccountType;
use App\Enums\ClientRegistrationStatus;
use App\Enums\ClientService;
use Database\Factories\ClientRegistrationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A prospective client's application to join the platform.
 *
 * Applications are stored separately from `clients` so that the review queue can
 * hold submissions that never become tenants, and so a tenant's billing record
 * is only created once onboarding is approved.
 *
 * @property int $id
 * @property string|null $company_name
 * @property ClientAccountType $account_type
 * @property string $contact_name
 * @property string $email
 * @property string $phone
 * @property string|null $country
 * @property string $timezone
 * @property string|null $website
 * @property list<string>|null $services
 * @property int|null $expected_monthly_volume
 * @property string|null $message
 * @property ClientRegistrationStatus $status
 * @property int|null $client_id
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $rejection_reason
 * @property string|null $ip_address
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'account_type', 'company_name', 'contact_name', 'email', 'phone', 'country', 'timezone',
    'website', 'services', 'expected_monthly_volume', 'message', 'status',
    'client_id', 'reviewed_by', 'reviewed_at', 'rejection_reason', 'ip_address',
])]
class ClientRegistration extends Model
{
    /** @use HasFactory<ClientRegistrationFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'account_type' => ClientAccountType::class,
            'status' => ClientRegistrationStatus::class,
            'services' => 'array',
            'expected_monthly_volume' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * The tenant created when the application was approved.
     *
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * The platform user who approved or rejected the application.
     *
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Whether the application is still waiting on a decision.
     */
    public function isPending(): bool
    {
        return $this->status === ClientRegistrationStatus::Pending;
    }

    /**
     * The services the applicant asked for.
     *
     * @return list<ClientService>
     */
    public function requestedServices(): array
    {
        $requested = $this->services ?? [];

        return array_values(array_filter(
            array_map(
                fn (string $service): ?ClientService => ClientService::tryFrom($service),
                $requested,
            ),
        ));
    }

    /**
     * Limit the query to applications that can still be decided.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function awaitingReview(Builder $query): void
    {
        $query->where('status', ClientRegistrationStatus::Pending->value);
    }
}
