<?php

namespace App\Services\Billing;

use App\Contracts\PaymentGateway;
use App\Enums\MobileMoneyProvider;
use App\Exceptions\PaymentGatewayException;
use App\Models\Payment;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Paystack, in both shapes this application needs it:
 *
 * - `initialize()` creates the hosted checkout the client is redirected to.
 * - `charge()` prompts a mobile money wallet directly, on our own form, and
 *   `submitOtp()` answers the code the wallet sends to the handset.
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
            status: is_string($data['status'] ?? null) ? $data['status'] : null,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function supportsMobileMoney(): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function charge(PaymentIntent $intent, MobileMoneyNumber $number, MobileMoneyProvider $provider): MobileMoneyCharge
    {
        $response = $this->http->post('charge', array_filter([
            'email' => $intent->email,
            'amount' => $intent->subunits(),
            'currency' => $intent->currency,
            'reference' => $intent->reference,
            'mobile_money' => [
                'phone' => $number->local,
                'provider' => $provider->value,
            ],
            'metadata' => $intent->metadata === [] ? null : $intent->metadata,
        ], fn (mixed $value): bool => $value !== null));

        $payload = $this->payload($response, 'charge', $intent->reference);

        Log::channel('billing')->info('paystack.charge.started', [
            'reference' => $intent->reference,
            'amount' => $intent->amount,
            'currency' => $intent->currency,
            'provider' => $provider->value,
        ]);

        return MobileMoneyCharge::fromGateway($intent->reference, $this->data($payload));
    }

    /**
     * {@inheritDoc}
     */
    public function submitOtp(string $reference, string $otp): MobileMoneyCharge
    {
        $response = $this->http->post('charge/submit_otp', [
            'otp' => $otp,
            'reference' => $reference,
        ]);

        $payload = $this->payload($response, 'submit_otp', $reference);

        return MobileMoneyCharge::fromGateway($reference, $this->data($payload));
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
