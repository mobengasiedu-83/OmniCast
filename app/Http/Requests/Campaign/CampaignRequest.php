<?php

namespace App\Http\Requests\Campaign;

use App\Enums\Channel;
use App\Enums\Permission;
use App\Models\Client;
use App\Services\PlatformConfiguration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CampaignRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::ManageCampaigns->value) ?? false;
    }

    /**
     * Scope the payload to the tenant of the signed-in user.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'client_id' => $this->user()?->client_id ?? $this->input('client_id'),
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
        $channel = $this->enum('channel', Channel::class);
        $requiresSms = $channel?->requiresSmsContent() ?? false;
        $requiresVoice = ($channel?->requiresVoiceContent() ?? false) && blank($this->input('voice_recording_path'));

        return [
            'client_id' => [Rule::requiredIf($this->user()?->isPlatformUser() ?? false), 'integer', Rule::exists('clients', 'id')],
            'name' => ['required', 'string', 'max:120'],
            'channel' => ['required', Rule::enum(Channel::class)],
            'contact_list_id' => ['nullable', 'integer', Rule::exists('contact_lists', 'id')->where('client_id', $clientId)],
            'sender_id' => ['nullable', 'integer', Rule::exists('sender_ids', 'id')->where('client_id', $clientId)],
            'caller_id' => ['nullable', 'integer', Rule::exists('caller_ids', 'id')->where('client_id', $clientId)],
            'sms_message' => [Rule::requiredIf($requiresSms), 'nullable', 'string', 'max:1600'],
            'voice_script' => [Rule::requiredIf($requiresVoice), 'nullable', 'string', 'max:4000'],
            'voice_recording_path' => ['nullable', 'string', 'max:2048'],
            'voice_max_duration_seconds' => ['nullable', 'integer', 'min:5', 'max:300'],
            'schedule_type' => ['required', Rule::in(['now', 'scheduled'])],
            'scheduled_at' => ['nullable', 'required_if:schedule_type,scheduled', 'date', 'after:now'],
            'timezone' => ['nullable', 'string', 'timezone:all'],
            'max_retries' => ['nullable', 'integer', 'min:1', 'max:10'],
            'retry_delay_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Reject a channel the tenant is not entitled to use.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $channel = $this->enum('channel', Channel::class);
            $clientId = $this->input('client_id');

            if ($channel === null || ! is_numeric($clientId)) {
                return;
            }

            $client = Client::query()->find((int) $clientId);

            if ($client === null || $client->allowsChannel($channel)) {
                if ($client === null) {
                    return;
                }
            }

            foreach ($channel->deliveryChannels() as $deliveryChannel) {
                if (! app(PlatformConfiguration::class)->channelEnabled($deliveryChannel->value, $client)) {
                    $validator->errors()->add('channel', 'The '.$deliveryChannel->label().' channel is currently unavailable.');
                }
            }

            if (! $client->allowsChannel($channel)) {
                $validator->errors()->add('channel', 'The '.$channel->label().' channel is not enabled for this client.');
            }
        });
    }

    /**
     * The campaign attributes that may be persisted.
     *
     * @return array<string, mixed>
     */
    public function campaignAttributes(): array
    {
        return $this->safe()->only([
            'client_id', 'name', 'channel', 'contact_list_id', 'sender_id', 'caller_id',
            'sms_message', 'voice_script', 'voice_recording_path', 'voice_max_duration_seconds',
            'schedule_type', 'scheduled_at', 'timezone', 'max_retries', 'retry_delay_minutes', 'notes',
        ]);
    }
}
