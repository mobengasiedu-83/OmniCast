<?php

namespace App\Http\Requests\Template;

use App\Enums\Permission;
use App\Models\TemplateCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TemplateCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::ManageTemplateCategories->value) ?? false;
    }

    /**
     * Scope the category to the tenant of the signed-in user.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'client_id' => $this->user()?->client_id ?? $this->input('client_id'),
            'sort_order' => (int) ($this->input('sort_order') ?? 0),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $clientId = $this->input('client_id');
        $routeCategory = $this->route('category');
        $categoryId = $routeCategory instanceof TemplateCategory ? $routeCategory->id : $routeCategory;

        return [
            'client_id' => [Rule::requiredIf($this->user()?->isPlatformUser() ?? false), 'integer', Rule::exists('clients', 'id')],
            'name' => [
                'required',
                'string',
                'max:80',
                Rule::unique('template_categories', 'name')
                    ->where('client_id', $clientId)
                    ->ignore($categoryId),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100'],
        ];
    }

    /**
     * The attributes that may be persisted on the category.
     *
     * @return array<string, mixed>
     */
    public function categoryAttributes(): array
    {
        return $this->safe()->only(['client_id', 'name', 'description', 'sort_order']);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'A category with that name already exists in this library.',
        ];
    }
}
