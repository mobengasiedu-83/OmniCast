<?php

namespace App\Http\Requests\Voice;

use App\Enums\VoiceCallEventType;
use App\Services\Voice\VoiceCallEventPayload;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * A call event posted by the switch to the signed voice webhook.
 *
 * The switch is not an authenticated user: the request is authorised by the
 * HMAC signature over the raw body, so a caller without the shared secret can
 * never move a call through its lifecycle. The payload keys are accepted under
 * the aliases the VitalPBX dialplan and the ARI adapters produce.
 */
class VoiceCallbackRequest extends FormRequest
{
    /**
     * Verify the shared-secret signature on the raw request body.
     */
    public function authorize(): bool
    {
        $secret = (string) config('voice.webhook.secret');

        if ($secret === '') {
            return false;
        }

        $signature = trim((string) $this->header('X-Omnicast-Signature', ''));

        if (str_starts_with($signature, 'sha256=')) {
            $signature = mb_substr($signature, 7);
        }

        return $signature !== ''
            && hash_equals(hash_hmac('sha256', $this->getContent(), $secret), $signature);
    }

    /**
     * Always answer with JSON, even when the switch omits the Accept header.
     */
    public function expectsJson(): bool
    {
        return true;
    }

    /**
     * Accept the header names the switch may use for the same value.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'reference' => $this->input('reference') ?? $this->input('call_reference') ?? $this->input('call'),
            'event' => $this->input('event') ?? $this->input('type'),
            'event_id' => $this->input('event_id') ?? $this->input('uniqueid'),
            'digits' => $this->input('digits') ?? $this->input('digit'),
            'duration' => $this->input('duration') ?? $this->input('duration_seconds') ?? $this->input('billsec'),
            'hangup_cause' => $this->input('hangup_cause') ?? $this->input('cause'),
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
            'reference' => ['required', 'string', 'max:64'],
            'event' => ['required', Rule::enum(VoiceCallEventType::class)],
            'event_id' => ['nullable', 'string', 'max:191'],
            'digits' => ['nullable', 'string', 'max:32'],
            'duration' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'hangup_cause' => ['nullable', 'string', 'max:64'],
            'provider_call_id' => ['nullable', 'string', 'max:191'],
            'occurred_at' => ['nullable', 'date'],
        ];
    }

    /**
     * The callback as the tracker understands it.
     */
    public function payload(): VoiceCallEventPayload
    {
        /** @var VoiceCallEventType $type */
        $type = VoiceCallEventType::from((string) $this->input('event'));

        $occurredAt = $this->input('occurred_at');

        return new VoiceCallEventPayload(
            reference: (string) $this->input('reference'),
            type: $type,
            digits: $this->filled('digits') ? (string) $this->input('digits') : null,
            durationSeconds: (int) $this->input('duration', 0),
            hangupCause: $this->filled('hangup_cause') ? (string) $this->input('hangup_cause') : null,
            providerEventId: $this->filled('event_id') ? (string) $this->input('event_id') : null,
            providerCallId: $this->filled('provider_call_id') ? (string) $this->input('provider_call_id') : null,
            occurredAt: is_string($occurredAt) && $occurredAt !== '' ? Carbon::parse($occurredAt) : null,
            payload: $this->all(),
        );
    }
}
