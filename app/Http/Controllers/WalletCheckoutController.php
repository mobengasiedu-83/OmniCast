<?php

namespace App\Http\Controllers;

use App\Exceptions\PaymentGatewayException;
use App\Http\Controllers\Concerns\ResolvesTenantClient;
use App\Http\Requests\Billing\WalletCheckoutRequest;
use App\Models\Payment;
use App\Services\Billing\CheckoutService;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Starts a wallet top up on the gateway's hosted checkout and picks the client
 * up again when they come back.
 *
 * The callback only ever asks the gateway what happened: a `?reference=` on the
 * return URL says nothing about whether the money was collected.
 */
class WalletCheckoutController extends Controller
{
    use ResolvesTenantClient;

    public function __construct(
        protected CheckoutService $checkout,
        protected WalletService $wallets,
    ) {}

    /**
     * Register the top up and hand the client to the gateway.
     */
    public function store(WalletCheckoutRequest $request): Response
    {
        $client = $this->tenantClient($request);

        abort_if($client === null, 403, 'Select a client account before funding its wallet.');

        Gate::authorize('topUp', $this->wallets->walletFor($client));

        try {
            $payment = $this->checkout->start(
                client: $client,
                user: $request->user(),
                packageKey: $request->packageKey(),
                callbackUrl: route('wallet.checkout.callback'),
            );
        } catch (PaymentGatewayException $exception) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => $exception->getMessage(),
            ]);
        }

        if ($payment->authorization_url === null) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => 'The payment gateway did not return a checkout page. Nothing has been charged.',
            ]);
        }

        return Inertia::location($payment->authorization_url);
    }

    /**
     * Settle the payment the gateway sent the client back with.
     */
    public function callback(Request $request): RedirectResponse
    {
        $reference = $this->referenceFrom($request);

        if ($reference === null) {
            return $this->backToWallet('error', 'The gateway returned without a payment reference.');
        }

        $payment = Payment::query()->where('reference', $reference)->first();

        if ($payment === null) {
            return $this->backToWallet('error', 'That payment reference does not belong to a top up on this platform.');
        }

        Gate::authorize('view', $payment);

        try {
            $payment = $this->checkout->verify($payment);
        } catch (PaymentGatewayException $exception) {
            return $this->backToWallet('error', $exception->getMessage());
        }

        if (! $payment->isSettled()) {
            return $this->backToWallet('error', $payment->failure_reason ?? 'The payment was not completed.');
        }

        return $this->backToWallet('success', $payment->currency.' '
            .number_format((float) $payment->amount_paid, 2).' added to the wallet.');
    }

    /**
     * Close a checkout the client walked away from.
     */
    public function cancel(Payment $payment): RedirectResponse
    {
        Gate::authorize('view', $payment);

        $this->checkout->abandon($payment);

        return $this->backToWallet('info', 'Checkout closed. No money was taken.');
    }

    /**
     * The reference Paystack (or the log driver) returns the client with.
     */
    protected function referenceFrom(Request $request): ?string
    {
        foreach (['reference', 'trxref'] as $key) {
            $reference = trim($request->string($key)->toString());

            if ($reference !== '') {
                return $reference;
            }
        }

        return null;
    }

    /**
     * Send the client back to the wallet with the outcome.
     */
    protected function backToWallet(string $type, string $message): RedirectResponse
    {
        return redirect()
            ->route('wallet.index')
            ->with('toast', ['type' => $type, 'message' => $message]);
    }
}
