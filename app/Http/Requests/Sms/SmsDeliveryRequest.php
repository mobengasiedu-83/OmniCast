<?php

namespace App\Http\Requests\Sms;

use App\Enums\SmsMessageStatus;
use App\Services\Sms\SmsDeliveryReport;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

/**
 * A delivery receipt posted by the network to the signed SMS webhook.
 *
 * The provider is not an authenticated user: a POST body is authorised by the
 * HMAC signature over the raw body, and a GET receipt (Kannel's `dlr-url`) by
 * the signature over its reference in `sig`. The payload keys are accepted
 * under the aliases SMPP aggregators, Kannel and JSON gateways produce.
 */
class SmsDeliveryRequest extends FormRequest
{
    /**
     * Verify the shared-secret signature on the receipt.
     */
    public function authorize(): bool
    {
        $secret = (string) config('sms.webhook.secret');

        if ($secret === '') {
            return false;
        }

        $signature = trim((string) $this->header('X-Omnicast-Signature', ''));

        if (str_starts_with($signature, 'sha256=')) {
            $signature = mb_substr($signature, 7);
        }

        if ($signature !== '') {
            return hash_equals(hash_hmac('sha256', $this->getContent(), $secret), $signature);
        }

        // A receipt that arrives as a querystring signs its own reference, the
        // way the delivery URL handed to the provider was built.
        $token = trim((string) $this->input('sig', ''));
        $reference = $this->referenceToken();

        return $token !== ''
            && $reference !== null
            && hash_equals(hash_hmac('sha256', $reference, $secret), $token);
    }

    /**
     * Always answer with JSON, even when the provider omits the Accept header.
     */
    public function expectsJson(): bool
    {
        return true;
    }

    /**
     * Accept the field names a provider may use for the same value.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'reference' => $this->referenceToken(),
            'status' => $this->input('status') ?? $this->input('delivery_status') ?? $this->input('stat') ?? $this->input('dlr_status'),
            'provider_status' => $this->input('provider_status') ?? $this->input('status') ?? $this->input('stat'),
            'event_id' => $this->input('event_id') ?? $this->input('receipt_id') ?? $this->input('dlr_id'),
            'provider_message_id' => $this->input('provider_message_id') ?? $this->input('message_id') ?? $this->input('msg_id') ?? $this->input('msgid') ?? $this->input('id'),
            'error' => $this->input('error') ?? $this->input('err') ?? $this->input('reason') ?? $this->input('error_message'),
            'occurred_at' => $this->input('occurred_at') ?? $this->input('timestamp') ?? $this->input('done_at'),
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
            'status' => [
                'required',
                'string',
                'max:32',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (SmsMessageStatus::fromProvider((string) $value) === null) {
                        $fail('The delivery status ['.$value.'] is not a status OmniCast understands.');
                    }
                },
            ],
            'provider_status' => ['nullable', 'string', 'max:64'],
            'provider_message_id' => ['nullable', 'string', 'max:191'],
            'event_id' => ['nullable', 'string', 'max:191'],
            'error' => ['nullable', 'string', 'max:255'],
            'occurred_at' => ['nullable', 'date'],
        ];
    }

    /**
     * The receipt as the tracker understands it.
     */
    public function payload(): SmsDeliveryReport
    {
        $status = SmsMessageStatus::fromProvider((string) $this->input('status'));
        $occurredAt = $this->input('occurred_at');
        $providerMessageId = $this->input('provider_message_id');
        $providerEventId = $this->input('event_id');
        $error = $this->input('error');

        return new SmsDeliveryReport(
            reference: (string) $this->input('reference'),
            status: $status ?? SmsMessageStatus::Failed,
            providerMessageId: is_string($providerMessageId) && $providerMessageId !== '' ? $providerMessageId : null,
            providerEventId: is_string($providerEventId) && $providerEventId !== '' ? $providerEventId : null,
            providerStatus: $this->filled('provider_status') ? (string) $this->input('provider_status') : null,
            error: is_string($error) && $error !== '' ? $error : null,
            occurredAt: is_string($occurredAt) && $occurredAt !== '' ? Carbon::parse($occurredAt) : null,
            payload: collect($this->all())->except(['sig'])->all(),
        );
    }

    /**
     * The OmniCast reference a receipt belongs to.
     */
    protected function referenceToken(): ?string
    {
        foreach (['reference', 'message_reference', 'ref', 'custom_id'] as $key) {
            $value = $this->input($key);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
