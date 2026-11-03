<?php

namespace App\Http\Requests\Users;

use App\Concerns\AssignableRoles;
use App\Concerns\PasswordValidationRules;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates the creation of a staff or client account.
 */
class StoreUserRequest extends FormRequest
{
    use AssignableRoles, PasswordValidationRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    /**
     * Normalise the payload before validation.
     */
    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        $this->merge([
            'email' => is_string($email) ? Str::lower(trim($email)) : $email,
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'job_title' => ['nullable', 'string', 'max:120'],
            'client_id' => ['nullable', 'integer', Rule::exists(Client::class, 'id')],
            'role' => ['required', 'string', Rule::in($this->assignableRoleValues())],
            'password' => $this->passwordRules(),
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
            },
        ];
    }
}
