<?php

namespace App\Models;

use App\Enums\ClientService;
use Database\Factories\ClientEntitlementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The service a client tenant is entitled to use, and its volume limits.
 *
 * A tenant without a row for a service is treated as unconfigured and therefore
 * allowed; only an explicit `is_enabled = false` row takes a service away.
 *
 * @property int $id
 * @property int $client_id
 * @property ClientService $service
 * @property bool $is_enabled
 * @property int|null $monthly_quota
 * @property int|null $daily_quota
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'client_id', 'service', 'is_enabled', 'monthly_quota', 'daily_quota', 'notes',
])]
class ClientEntitlement extends Model
{
    /** @use HasFactory<ClientEntitlementFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'service' => ClientService::class,
            'is_enabled' => 'boolean',
            'monthly_quota' => 'integer',
            'daily_quota' => 'integer',
        ];
    }

    /**
     * The tenant the entitlement belongs to.
     *
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Limit the query to the entitlements of one tenant.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function forClient(Builder $query, Client $client): void
    {
        $query->where($query->qualifyColumn('client_id'), $client->id);
    }

    /**
     * Human readable quota summary for the table.
     */
    public function quotaLabel(): string
    {
        if ($this->monthly_quota === null && $this->daily_quota === null) {
            return 'Unlimited';
        }

        $parts = [];

        if ($this->monthly_quota !== null) {
            $parts[] = number_format($this->monthly_quota).' / month';
        }

        if ($this->daily_quota !== null) {
            $parts[] = number_format($this->daily_quota).' / day';
        }

        return implode(' · ', $parts);
    }
}
