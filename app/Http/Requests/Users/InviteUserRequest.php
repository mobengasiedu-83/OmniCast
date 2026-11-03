<?php

namespace App\Http\Requests\Users;

use App\Concerns\AssignableRoles;
use App\Models\Client;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates an invitation to the platform team or to a client tenant.
 */
class InviteUserRequest extends FormRequest
{
    use AssignableRoles;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', UserInvitation::class) ?? false;
    }

    /**
     * Normalise the payload before validation.
     */
    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        $this->merge([
            'email' => is_string($email) ? Str::lower(trim($email)) : $email,
            'client_id' => $this->requestedClientId(),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'role' => ['required', 'string', Rule::in($this->assignableRoleValues())],
            'client_id' => ['nullable', 'integer', Rule::exists(Client::class, 'id')],
        ];
    }

    /**
     * The validated invitation payload in the shape the issuer expects.
     *
     * @return array{email: string, name: string|null, role: string, client_id: int|null}
     */
    public function invitationAttributes(): array
    {
        return [
            'email' => $this->string('email')->trim()->lower()->toString(),
            'name' => $this->filled('name') ? $this->string('name')->trim()->toString() : null,
            'role' => $this->string('role')->toString(),
            'client_id' => $this->requestedClientId(),
        ];
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->validateRoleTenant($validator);

                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $email = $this->input('email');

                $duplicate = UserInvitation::query()
                    ->outstanding()
                    ->where('email', $email)
                    ->where(function (Builder $query): void {
                        $clientId = $this->requestedClientId();

                        if ($clientId === null) {
                            $query->whereNull('client_id');

                            return;
                        }

                        $query->where('client_id', $clientId);
                    })
                    ->exists();

                if ($duplicate) {
                    $validator->errors()->add(
                        'email',
                        __('There is already a pending invitation for this email address.'),
                    );
                }
            },
        ];
    }
}
