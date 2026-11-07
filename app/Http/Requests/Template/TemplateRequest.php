<?php

namespace App\Http\Requests\Template;

use App\Enums\Channel;
use App\Enums\Permission;
use App\Enums\TemplateStatus;
use App\Models\MessageTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TemplateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::ManageTemplates->value) ?? false;
    }

    /**
     * Scope the template to the tenant of the signed-in user and settle the
     * defaults the editor leaves out.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'client_id' => $this->user()?->client_id ?? $this->input('client_id'),
            'language' => Str::lower(trim((string) ($this->input('language') ?: 'en'))),
            'subject' => $this->filled('subject') ? trim((string) $this->input('subject')) : null,
            'description' => $this->filled('description') ? trim((string) $this->input('description')) : null,
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
        $channel = $this->enum('channel', Channel::class) ?? Channel::Sms;
        $routeTemplate = $this->route('template');
        $templateId = $routeTemplate instanceof MessageTemplate ? $routeTemplate->id : $routeTemplate;

        return [
            'client_id' => [Rule::requiredIf($this->user()?->isPlatformUser() ?? false), 'integer', Rule::exists('clients', 'id')],
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('message_templates', 'name')
                    ->where('client_id', $clientId)
                    ->where('channel', $channel->value)
                    ->whereNull('deleted_at')
                    ->ignore($templateId),
            ],
            'channel' => ['required', Rule::enum(Channel::class)],
            'language' => ['required', 'string', 'size:2', 'alpha'],
            'template_category_id' => [
                'nullable',
                'integer',
                Rule::exists('template_categories', 'id')
                    ->where(fn ($query) => $query->whereNull('client_id')->orWhere('client_id', $clientId)),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'subject' => [Rule::requiredIf($channel->requiresEmailContent()), 'nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:'.$this->bodyLimit($channel)],
            'status' => [
                'nullable',
                Rule::in([
                    TemplateStatus::Draft->value,
                    TemplateStatus::Pending->value,
                    TemplateStatus::Active->value,
                ]),
            ],
        ];
    }

    /**
     * The attributes that may be persisted on the template.
     *
     * @return array<string, mixed>
     */
    public function templateAttributes(): array
    {
        return $this->safe()->only([
            'client_id', 'name', 'channel', 'language', 'template_category_id',
            'description', 'subject', 'body', 'status',
        ]);
    }

    /**
     * The status the author asked for, defaulting to a tenant draft.
     */
    public function requestedStatus(): TemplateStatus
    {
        return TemplateStatus::tryFrom((string) $this->input('status')) ?? TemplateStatus::Draft;
    }

    /**
     * Wording that explains what each channel's copy needs.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'This client already has a template with that name on this channel.',
            'subject.required' => 'An email template needs a subject line.',
            'body.required' => 'A template needs the message copy before it can be saved.',
        ];
    }

    /**
     * The longest copy a single message may hold on the channel.
     */
    protected function bodyLimit(Channel $channel): int
    {
        return match ($channel) {
            Channel::Sms => 1600,
            Channel::MultiChannel => 1600,
            Channel::Voice => 4000,
            Channel::Email => 100000,
        };
    }
}
