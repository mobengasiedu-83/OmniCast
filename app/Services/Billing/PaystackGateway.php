<?php

namespace App\Services\Billing;

use App\Contracts\PaymentGateway;
use App\Exceptions\PaymentGatewayException;
use App\Models\Payment;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Paystack's hosted checkout: the client is redirected to Paystack, pays with a
 * card or mobile money, and comes back to the callback URL.
 *
 * Amounts travel in the subunit (pesewas for GHS, kobo for NGN): GHS 51.25 is
 * sent as 5125. The secret key is only ever read from configuration.
 */
class PaystackGateway implements PaymentGateway
{
    private readonly PendingRequest $http;

    public function __construct()
    {
        $this->http = Http::baseUrl(rtrim((string) config('billing.paystack.base_url', 'https://api.paystack.co'), '/'))
            ->withToken((string) config('billing.paystack.secret_key'))
            ->acceptJson()
            ->asJson()
            ->connectTimeout(10)
            ->timeout((int) config('billing.paystack.timeout', 20));
    }

    /**
     * {@inheritDoc}
     */
    public function name(): string
    {
        return 'paystack';
    }

    /**
     * {@inheritDoc}
     */
    public function initialize(PaymentIntent $intent): PaymentInitiation
    {
        $response = $this->http->post('transaction/initialize', array_filter([
            'email' => $intent->email,
            'amount' => $intent->subunits(),
            'currency' => $intent->currency,
            'reference' => $intent->reference,
            'callback_url' => $intent->callbackUrl,
            'channels' => $intent->channels === [] ? null : $intent->channels,
            'metadata' => $intent->metadata === [] ? null : $intent->metadata,
        ], fn (mixed $value): bool => $value !== null));

        $payload = $this->payload($response, 'initialize', $intent->reference);
        $data = $this->data($payload);

        $authorizationUrl = $data['authorization_url'] ?? null;

        if (! is_string($authorizationUrl) || $authorizationUrl === '') {
            throw new PaymentGatewayException('Paystack did not return a checkout URL. Nothing has been charged.');
        }

        Log::channel('billing')->info('paystack.checkout.initialized', [
            'reference' => $intent->reference,
            'amount' => $intent->amount,
            'currency' => $intent->currency,
        ]);

        return new PaymentInitiation(
            authorizationUrl: $authorizationUrl,
            accessCode: is_string($data['access_code'] ?? null) ? $data['access_code'] : null,
            providerReference: is_string($data['reference'] ?? null) ? $data['reference'] : $intent->reference,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function verify(Payment $payment): PaymentVerification
    {
        $response = $this->http->get('transaction/verify/'.rawurlencode($payment->reference));

        $payload = $this->payload($response, 'verify', $payment->reference);
        $data = $this->data($payload);

        $successful = ($data['status'] ?? null) === 'success';
        $amount = isset($data['amount']) && is_numeric($data['amount']) ? ((int) $data['amount']) / 100 : 0.0;
        $paidAt = $data['paid_at'] ?? $data['paidAt'] ?? null;
        $failureReason = $data['gateway_response'] ?? $payload['message'] ?? null;

        return new PaymentVerification(
            successful: $successful,
            amount: round((float) $amount, 2),
            currency: is_string($data['currency'] ?? null) ? $data['currency'] : $payment->currency,
            providerReference: is_string($data['reference'] ?? null) ? $data['reference'] : null,
            channel: is_string($data['channel'] ?? null) ? $data['channel'] : null,
            paidAt: is_string($paidAt) && $paidAt !== '' ? Carbon::parse($paidAt) : ($successful ? now() : null),
            failureReason: $successful ? null : (is_string($failureReason) ? $failureReason : 'The payment was not completed.'),
            raw: $data,
        );
    }

    /**
     * Read a Paystack envelope, raising when the call itself failed.
     *
     * @return array<string, mixed>
     */
    protected function payload(Response $response, string $action, string $reference): array
    {
        /** @var mixed $body */
        $body = $response->json();
        /** @var array<string, mixed> $payload */
        $payload = is_array($body) ? $body : [];

        if ($response->failed() || ($payload['status'] ?? null) === false) {
            $message = is_string($payload['message'] ?? null)
                ? $payload['message']
                : 'Paystack returned HTTP '.$response->status().'.';

            Log::channel('billing')->warning('paystack.request.failed', [
                'action' => $action,
                'reference' => $reference,
                'status' => $response->status(),
                'message' => $message,
            ]);

            throw new PaymentGatewayException($message);
        }

        return $payload;
    }

    /**
     * The `data` object of a Paystack envelope.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function data(array $payload): array
    {
        return is_array($payload['data'] ?? null) ? $payload['data'] : [];
    }
}
