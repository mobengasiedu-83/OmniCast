<?php

namespace App\Services;

use App\Enums\ClientRegistrationStatus;
use App\Enums\ClientService;
use App\Enums\ClientStatus;
use App\Enums\Role as RoleEnum;
use App\Models\Client;
use App\Models\ClientEntitlement;
use App\Models\ClientRegistration;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Turns a reviewed client application into a tenant.
 *
 * The tenant is created pending activation so that compliance can finish the
 * document checks, while the applicant is invited immediately as its first
 * client administrator.
 */
class ClientOnboarding
{
    /**
     * Create a new onboarding service.
     */
    public function __construct(protected InvitationIssuer $invitations) {}

    /**
     * Approve an application and create the tenant it describes.
     *
     * @param  array{name?: string|null, code?: string|null, credit_limit?: float|int|string|null}  $overrides
     */
    public function approve(ClientRegistration $registration, User $reviewer, array $overrides = []): Client
    {
        $this->ensurePending($registration);

        $client = DB::transaction(function () use ($registration, $reviewer, $overrides): Client {
            $client = Client::query()->create([
                'name' => $overrides['name'] ?? $registration->company_name,
                'code' => $overrides['code'] ?? $this->generateCode($registration->company_name),
                'email' => $registration->email,
                'phone' => $registration->phone,
                'timezone' => $registration->timezone,
                'status' => ClientStatus::Pending,
                'status_changed_at' => now(),
                'credit_limit' => $overrides['credit_limit'] ?? 0,
                'notes' => $registration->message,
                'created_by' => $reviewer->id,
            ]);

            $this->seedEntitlements($client, $registration);

            $registration->forceFill([
                'status' => ClientRegistrationStatus::Approved,
                'client_id' => $client->id,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ])->save();

            activity()
                ->performedOn($client)
                ->causedBy($reviewer)
                ->withProperties([
                    'registration_id' => $registration->id,
                    'company_name' => $registration->company_name,
                    'services' => $registration->services,
                ])
                ->log('approved client registration');

            return $client;
        });

        $this->invitations->issue([
            'email' => $registration->email,
            'name' => $registration->contact_name,
            'role' => RoleEnum::ClientAdmin->value,
            'client_id' => $client->id,
        ], $reviewer);

        return $client;
    }

    /**
     * Decline an application, keeping the reason for the audit trail.
     */
    public function reject(ClientRegistration $registration, User $reviewer, string $reason): ClientRegistration
    {
        $this->ensurePending($registration);

        $registration->forceFill([
            'status' => ClientRegistrationStatus::Rejected,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'rejection_reason' => $reason,
        ])->save();

        activity()
            ->performedOn($registration)
            ->causedBy($reviewer)
            ->withProperties([
                'reason' => $reason,
                'company_name' => $registration->company_name,
            ])
            ->log('rejected client registration');

        return $registration;
    }

    /**
     * Enable the services the applicant asked for; unrequested services are
     * recorded as disabled so an administrator can switch them on later.
     */
    protected function seedEntitlements(Client $client, ClientRegistration $registration): void
    {
        $requested = $registration->services ?? [];

        foreach (ClientService::cases() as $service) {
            ClientEntitlement::query()->create([
                'client_id' => $client->id,
                'service' => $service,
                'is_enabled' => $requested === [] || in_array($service->value, $requested, true),
            ]);
        }
    }

    /**
     * Derive a readable, unique tenant code from the company name.
     */
    protected function generateCode(string $companyName): string
    {
        $stem = Str::upper(Str::substr(Str::slug($companyName, ''), 0, 6));

        if ($stem === '') {
            $stem = 'CLIENT';
        }

        do {
            $code = $stem.'-'.Str::upper(Str::random(4));
        } while (Client::withTrashed()->where('code', $code)->exists());

        return $code;
    }

    /**
     * Guard against deciding the same application twice.
     */
    protected function ensurePending(ClientRegistration $registration): void
    {
        if (! $registration->isPending()) {
            throw new RuntimeException('This client application has already been decided.');
        }
    }
}
