<?php

namespace App\Http\Controllers;

use App\Enums\MobileMoneyProvider;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentGatewayException;
use App\Http\Controllers\Concerns\ResolvesTenantClient;
use App\Http\Requests\Billing\MobileMoneyCheckoutRequest;
use App\Http\Requests\Billing\MobileMoneyOtpRequest;
use App\Models\Payment;
use App\Models\Wallet;
use App\Services\Billing\CheckoutService;
use App\Services\Billing\CreditEstimator;
use App\Services\Billing\PackageCatalogue;
use App\Services\Billing\PaymentGatewayManager;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Funds a wallet by prompting a mobile money wallet on our own form.
 *
 * Unlike the hosted checkout, the client never leaves the application: the
 * charge is registered against the gateway's charge API, the wallet texts a
 * code or asks for approval on the handset, and the wallet is only credited
 * once `verify()` says the money arrived.
 */
class MobileMoneyCheckoutController extends Controller
{
    use ResolvesTenantClient;

    public function __construct(
        protected CheckoutService $checkout,
        protected WalletService $wallets,
        protected PackageCatalogue $packages,
        protected PaymentGatewayManager $gateways,
        protected CreditEstimator $estimator,
    ) {}

    /**
     * The form where the client picks a package and types its wallet number.
     */
    public function create(Request $request): Response|RedirectResponse
    {
        Gate::authorize('viewAny', Wallet::class);

        $client = $this->tenantClient($request);

        if ($client === null) {
            return redirect()->route('wallet.index')->with('toast', [
                'type' => 'info',
                'message' => 'Select a client account before paying by mobile money.',
            ]);
        }

        $selected = $request->string('package')->toString();

        return Inertia::render('wallet/mobile-money', [
            'client' => ['id' => $client->id, 'name' => $client->name],
            'currency' => $this->packages->currency(),
            'packages' => $this->packages->all(),
            'providers' => MobileMoneyProvider::options(),
            'selected' => in_array($selected, $this->packages->keys(), true) ? $selected : null,
            'selected_amount' => $request->filled('amount') ? $this->estimator->clamp($request->float('amount')) : null,
            'rates' => $this->estimator->rates(),
            'slider' => $this->estimator->slider(),
            'dynamic_pricing' => $this->estimator->dynamicPricingEnabled(),
            'limits' => $this->packages->limits(),
            'payment' => null,
            'gateway' => $this->gatewayProps(),
        ]);
    }

    /**
     * Register the top up and ask the gateway to prompt the wallet.
     */
    public function store(MobileMoneyCheckoutRequest $request): RedirectResponse
    {
        $client = $this->tenantClient($request);

        abort_if($client === null, 403, 'Select a client account before funding its wallet.');

        Gate::authorize('topUp', $this->wallets->walletFor($client));

        try {
            $payment = $this->checkout->startMobileMoney(
                client: $client,
                user: $request->user(),
                packageKey: $request->packageKey(),
                number: $request->number(),
                provider: $request->provider(),
                amount: $request->amount(),
            );
        } catch (PaymentGatewayException $exception) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => $exception->getMessage(),
            ]);
        }

        if ($payment->isSettled()) {
            return $this->settled($payment);
        }

        if ($payment->status === PaymentStatus::Failed) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => $payment->failure_reason ?? 'The wallet did not approve the payment.',
            ]);
        }

        return $this->awaiting($payment);
    }

    /**
     * The prompt, the code form or the wait, for a charge already registered.
     */
    public function show(Payment $payment): Response|RedirectResponse
    {
        Gate::authorize('view', $payment);

        if ($payment->isSettled()) {
            return $this->settled($payment);
        }

        return Inertia::render('wallet/mobile-money', [
            'client' => ['id' => $payment->client_id, 'name' => $payment->client->name],
            'currency' => $payment->currency,
            'packages' => $this->packages->all(),
            'providers' => MobileMoneyProvider::options(),
            'selected' => $payment->package_key,
            'limits' => $this->packages->limits(),
            'payment' => $this->presentPayment($payment),
            'gateway' => $this->gatewayProps(),
        ]);
    }

    /**
     * Submit the code the wallet texted the payer.
     */
    public function submitOtp(MobileMoneyOtpRequest $request, Payment $payment): RedirectResponse
    {
        Gate::authorize('view', $payment);

        try {
            $payment = $this->checkout->submitOtp($payment, $request->otp());
        } catch (PaymentGatewayException $exception) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => $exception->getMessage(),
            ]);
        }

        return $this->afterCharge($payment);
    }

    /**
     * Ask the wallet whether the prompt has been approved yet.
     */
    public function refresh(Payment $payment): RedirectResponse
    {
        Gate::authorize('view', $payment);

        try {
            $payment = $this->checkout->verify($payment);
        } catch (PaymentGatewayException $exception) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => $exception->getMessage(),
            ]);
        }

        return $this->afterCharge($payment);
    }

    /**
     * Send the client wherever the charge has got to.
     */
    protected function afterCharge(Payment $payment): RedirectResponse
    {
        if ($payment->isSettled()) {
            return $this->settled($payment);
        }

        if ($payment->status === PaymentStatus::Failed) {
            return $this->awaiting(
                $payment,
                'error',
                $payment->failure_reason ?? 'The wallet did not approve the payment.',
            );
        }

        return $this->awaiting(
            $payment,
            'info',
            'The wallet has not confirmed yet. Approve the prompt on the phone and check again.',
        );
    }

    /**
     * Back to the prompt, with whatever the wallet last said.
     */
    protected function awaiting(Payment $payment, string $type = 'info', ?string $message = null): RedirectResponse
    {
        return redirect()
            ->route('wallet.mobile-money.show', $payment)
            ->with('toast', [
                'type' => $type,
                'message' => $message ?? $this->chargeMessage($payment),
            ]);
    }

    /**
     * The last thing the wallet said, or what the client should do next.
     */
    protected function chargeMessage(Payment $payment): string
    {
        $mobileMoney = $payment->mobileMoney() ?? [];

        $message = $mobileMoney['message'] ?? null;

        return is_string($message) && $message !== ''
            ? $message
            : 'Approve the payment on your phone.';
    }

    /**
     * The charge landed: the wallet is credited and the receipt raised, so the
     * client is sent on to their dashboard.
     */
    protected function settled(Payment $payment): RedirectResponse
    {
        return redirect()->route('dashboard')->with('toast', [
            'type' => 'success',
            'message' => $payment->currency.' '.number_format((float) $payment->amount_paid, 2)
                .' added to the wallet.',
        ]);
    }

    /**
     * The charge in the shape the checkout page renders.
     *
     * @return array<string, mixed>
     */
    protected function presentPayment(Payment $payment): array
    {
        $mobileMoney = $payment->mobileMoney() ?? [];

        $requiresOtp = (bool) ($mobileMoney['requires_otp'] ?? false);

        return [
            'id' => $payment->id,
            'reference' => $payment->reference,
            'description' => $payment->description,
            'status' => $payment->status->value,
            'status_label' => $payment->status->label(),
            'amount' => (float) $payment->amount,
            'currency' => $payment->currency,
            'phone' => $payment->mobileMoneyNumber(),
            'provider' => $mobileMoney['provider'] ?? null,
            'provider_label' => $mobileMoney['provider_label'] ?? null,
            'charge_status' => $mobileMoney['status'] ?? null,
            'message' => $mobileMoney['message'] ?? null,
            'requires_otp' => $requiresOtp,
            'awaits_approval' => $payment->status->isOpen() && ! $requiresOtp,
            'is_open' => $payment->status->isOpen(),
            'failure_reason' => $payment->failure_reason,
            'created_at' => $payment->created_at?->toIso8601String(),
        ];
    }

    /**
     * What the page needs to know about the configured gateway.
     *
     * @return array{driver: string, live: bool, mobile_money: bool}
     */
    protected function gatewayProps(): array
    {
        return [
            'driver' => (string) config('billing.driver', 'paystack'),
            'live' => $this->gateways->isLive(),
            'mobile_money' => $this->gateways->supportsMobileMoney(),
        ];
    }
}
