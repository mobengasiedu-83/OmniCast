<?php

namespace App\Http\Requests\Clients;

use App\Enums\ClientService;
use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the service entitlement grid of a tenant.
 */
class UpdateClientEntitlementsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $client = $this->route('client');

        return $client instanceof Client && ($this->user()?->can('update', $client) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'entitlements' => ['required', 'array', 'min:1'],
            'entitlements.*.service' => ['required', 'string', Rule::enum(ClientService::class)],
            'entitlements.*.is_enabled' => ['required', 'boolean'],
            'entitlements.*.monthly_quota' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'entitlements.*.daily_quota' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'entitlements.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * The entitlement rows keyed by service.
     *
     * @return array<string, array{is_enabled: bool, monthly_quota: int|null, daily_quota: int|null, notes: string|null}>
     */
    public function entitlementRows(): array
    {
        /** @var list<array{service: string, is_enabled: bool, monthly_quota?: int|null, daily_quota?: int|null, notes?: string|null}> $rows */
        $rows = $this->validated('entitlements');

        $keyed = [];

        foreach ($rows as $row) {
            $keyed[$row['service']] = [
                'is_enabled' => (bool) $row['is_enabled'],
                'monthly_quota' => $row['monthly_quota'] ?? null,
                'daily_quota' => $row['daily_quota'] ?? null,
                'notes' => $row['notes'] ?? null,
            ];
        }

        return $keyed;
    }
}
