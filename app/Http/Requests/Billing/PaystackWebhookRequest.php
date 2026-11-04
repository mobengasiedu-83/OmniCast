<?php

namespace App\Http\Requests\Billing;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A Paystack webhook. The payload is never trusted: the request is only
 * authorised when its raw body matches the signature Paystack computed with
 * the shared secret, and `charge.success` is re-verified against the API.
 */
class PaystackWebhookRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $secret = (string) (config('billing.paystack.webhook_secret') ?: config('billing.paystack.secret_key'));

        if ($secret === '') {
            return false;
        }

        $signature = (string) $this->header('X-Paystack-Signature', '');

        if ($signature === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha512', $this->getContent(), $secret), $signature);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'event' => ['required', 'string', 'max:64'],
            'data' => ['nullable', 'array'],
        ];
    }

    /**
     * Always answer with JSON so Paystack sees a status code, not a redirect.
     */
    public function expectsJson(): bool
    {
        return true;
    }

    /**
     * The event name, for example `charge.success`.
     */
    public function event(): string
    {
        return (string) $this->string('event');
    }

    /**
     * The whole payload, passed to the checkout service unmodified.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->all();
    }
}
