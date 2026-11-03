<?php

namespace App\Http\Requests\Users;

use App\Concerns\AssignableRoles;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates edits to an existing account.
 */
class UpdateUserRequest extends FormRequest
{
    use AssignableRoles;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->route('user');

        return $user instanceof User && ($this->user()?->can('update', $user) ?? false);
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
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                $user instanceof User
                    ? Rule::unique(User::class)->ignore($user->id)
                    : Rule::unique(User::class),
            ],
            'job_title' => ['nullable', 'string', 'max:120'],
            'client_id' => ['nullable', 'integer', Rule::exists(Client::class, 'id')],
            'role' => ['required', 'string', Rule::in($this->assignableRoleValues())],
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
                $user = $this->route('user');

                if ($user instanceof User && $user->id === $this->user()?->id && $user->primaryRole()?->value !== $this->input('role')) {
                    $validator->errors()->add('role', __('You cannot change your own role.'));
                }

                $this->validateRoleTenant($validator);
            },
        ];
    }
}
