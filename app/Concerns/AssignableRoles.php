<?php

namespace App\Concerns;

use App\Enums\Role as RoleEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Shared role and tenant rules for account and invitation requests.
 *
 * @phpstan-require-extends FormRequest
 */
trait AssignableRoles
{
    /**
     * The role values the authenticated user may hand out.
     *
     * @return list<string>
     */
    protected function assignableRoleValues(): array
    {
        $actor = $this->user();

        $roles = $actor !== null && $actor->isPlatformUser()
            ? array_values(array_filter(
                RoleEnum::cases(),
                fn (RoleEnum $role): bool => $role !== RoleEnum::SuperAdmin || $actor->isSuperAdmin(),
            ))
            : RoleEnum::clientRoles();

        return array_map(fn (RoleEnum $role): string => $role->value, $roles);
    }

    /**
     * The client the new account belongs to, or null for the platform team.
     *
     * Client users can only ever create accounts inside their own tenant.
     */
    protected function requestedClientId(): ?int
    {
        $actor = $this->user();

        if ($actor !== null && $actor->client_id !== null) {
            return $actor->client_id;
        }

        $clientId = $this->input('client_id');

        return is_numeric($clientId) ? (int) $clientId : null;
    }

    /**
     * Reject a platform role tied to a tenant and a client role without one.
     */
    protected function validateRoleTenant(Validator $validator): void
    {
        $role = RoleEnum::tryFrom((string) $this->input('role'));

        if ($role === null) {
            return;
        }

        $clientId = $this->requestedClientId();

        if ($role->isPlatformRole() && $clientId !== null) {
            $validator->errors()->add('client_id', __('Platform team members are not tied to a client.'));
        }

        if (! $role->isPlatformRole() && $clientId === null) {
            $validator->errors()->add('client_id', __('Select the client this user belongs to.'));
        }
    }
}
