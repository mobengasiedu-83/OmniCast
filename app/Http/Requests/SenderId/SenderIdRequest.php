<?php

namespace App\Http\Requests\SenderId;

use App\Enums\Permission;
use App\Enums\SenderIdChannel;
use App\Models\SenderId;
use App\Services\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SenderIdRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::ManageSenderIds->value) ?? false;
    }

    /**
     * Scope the payload to the tenant of the signed-in user and normalise the
     * identifier for the selected channel.
     */
    protected function prepareForValidation(): void
    {
        $country = $this->input('country');

        $this->merge([
            'client_id' => $this->user()?->client_id ?? $this->input('client_id'),
            'name' => $this->normaliseName($this->channel(), $this->input('name')),
            'country' => is_string($country) && trim($country) !== '' ? Str::upper(trim($country)) : null,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $channel = $this->channel();
        $routeSenderId = $this->route('senderId');
        $senderIdId = $routeSenderId instanceof SenderId ? $routeSenderId->id : $routeSenderId;

        return [
            'client_id' => [Rule::requiredIf($this->user()?->isPlatformUser() ?? false), 'integer', Rule::exists('clients', 'id')],
            'channel' => ['required', Rule::enum(SenderIdChannel::class)],
            'name' => [
                'required',
                'string',
                ...$this->identifierRules($channel),
                Rule::unique('sender_ids', 'name')
                    ->where('client_id', $this->input('client_id'))
                    ->where('channel', $channel->value)
                    ->whereNull('deleted_at')
                    ->ignore($senderIdId),
            ],
            'country' => [
                Rule::requiredIf($channel->requiresCountry()),
                'nullable',
                'string',
                'size:2',
                'regex:/^[A-Z]{2}$/',
            ],
            'purpose' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    /**
     * The attributes that may be persisted on the sender ID.
     *
     * @return array<string, mixed>
     */
    public function senderIdAttributes(): array
    {
        return $this->safe()->only(['client_id', 'channel', 'name', 'country', 'purpose']);
    }

    /**
     * Wording that explains the identifier rules of the selected channel.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $channel = $this->channel();

        return match ($channel) {
            SenderIdChannel::Sms => [
                'name.min' => "An SMS sender ID needs at least {$channel->minLength()} characters.",
                'name.max' => "An SMS sender ID can be at most {$channel->maxLength()} characters long, including spaces.",
                'name.regex' => 'An SMS sender ID may use letters in any case, digits'
                    .($channel->allowsSpaces() ? ', spaces' : '')
                    .' and symbols, and must contain at least one letter or digit.',
            ],
            SenderIdChannel::Voice => [
                'name.regex' => 'A caller number must be in international format, for example +233240000000.',
            ],
            SenderIdChannel::Email => [],
        };
    }

    /**
     * The identifier rules that apply to the selected channel.
     *
     * @return list<string>
     */
    protected function identifierRules(SenderIdChannel $channel): array
    {
        return match ($channel) {
            SenderIdChannel::Sms => [
                'min:'.$channel->minLength(),
                'max:'.$channel->maxLength(),
                'regex:/^(?=.*[A-Za-z0-9])'.($channel->allowsSpaces() ? '[\x20-\x7E]' : '[\x21-\x7E]').'+$/',
            ],
            SenderIdChannel::Email => ['email:rfc', 'max:'.$channel->maxLength()],
            SenderIdChannel::Voice => ['max:20', 'regex:/^\+[1-9]\d{7,14}$/'],
        };
    }

    /**
     * Normalise the identifier so the same value is never stored twice.
     */
    protected function normaliseName(SenderIdChannel $channel, mixed $name): mixed
    {
        if (! is_string($name)) {
            return $name;
        }

        return match ($channel) {
            SenderIdChannel::Sms => $this->normaliseSmsName($name),
            SenderIdChannel::Email => Str::lower(trim($name)),
            SenderIdChannel::Voice => app(PhoneNumber::class)->normalize($name),
        };
    }

    /**
     * Keep an SMS brand exactly as it was typed: case is significant and spaces
     * and symbols belong to the identity, so only invisible characters and
     * repeated whitespace are normalised away.
     */
    protected function normaliseSmsName(string $name): string
    {
        $name = preg_replace('/[\x00-\x1F\x7F]/', '', $name) ?? '';
        $name = preg_replace('/\s+/', ' ', $name) ?? '';

        return trim($name);
    }

    /**
     * The channel being requested, defaulting to SMS like the rest of the form.
     */
    protected function channel(): SenderIdChannel
    {
        return SenderIdChannel::tryFrom((string) $this->input('channel')) ?? SenderIdChannel::Sms;
    }
}
