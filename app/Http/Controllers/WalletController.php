<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Enums\ReservationStatus;
use App\Http\Controllers\Concerns\ResolvesTenantClient;
use App\Http\Requests\Wallet\TopUpWalletRequest;
use App\Models\BillingReservation;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\Billing\PackageCatalogue;
use App\Services\Billing\PaymentGatewayManager;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class WalletController extends Controller
{
    use ResolvesTenantClient;

    public function __construct(
        protected WalletService $wallets,
        protected PackageCatalogue $packages,
        protected PaymentGatewayManager $gateways,
    ) {}

    /**
     * Show the client wallet, its ledger and open reservations.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Wallet::class);

        $client = $this->tenantClient($request);
        $wallet = $client !== null ? $this->wallets->walletFor($client) : null;

        return Inertia::render('wallet/index', [
            'wallet' => $wallet === null ? null : [
                'id' => $wallet->id,
                'currency' => $wallet->currency,
                'balance' => (float) $wallet->balance,
                'reserved' => (float) $wallet->reserved_balance,
                'available' => $wallet->availableBalance(),
                'low_balance_threshold' => (float) $wallet->low_balance_threshold,
                'is_low' => $wallet->isLow(),
            ],
            'transactions' => $wallet === null ? null : $wallet->transactions()
                ->latest()
                ->paginate(20)
                ->withQueryString()
                ->through(fn (WalletTransaction $transaction): array => [
                    'id' => $transaction->id,
                    'type' => $transaction->type->value,
                    'type_label' => $transaction->type->label(),
                    'amount' => (float) $transaction->amount,
                    'balance_after' => (float) $transaction->balance_after,
                    'description' => $transaction->description,
                    'reference' => $transaction->reference,
                    'increases_balance' => $transaction->type->increasesAvailableBalance(),
                    'created_at' => $transaction->created_at?->toIso8601String(),
                ]),
            'reservations' => $wallet === null ? [] : $wallet->reservations()
                ->where('status', ReservationStatus::Held->value)
                ->with('campaign:id,reference,name')
                ->latest()
                ->get()
                ->map(fn (BillingReservation $reservation): array => [
                    'id' => $reservation->id,
                    'amount' => (float) $reservation->amount,
                    'captured_amount' => (float) $reservation->captured_amount,
                    'campaign' => $reservation->campaign?->name,
                    'reference' => $reservation->campaign?->reference,
                    'created_at' => $reservation->created_at?->toIso8601String(),
                ])
                ->all(),
            'can' => [
                'checkout' => $request->user()->can(Permission::TopUpWallet->value),
                'manual_credit' => $request->user()->isPlatformUser()
                    && $request->user()->can(Permission::TopUpWallet->value),
            ],
            'clients' => $this->selectableClients($request),
            'client' => $client === null ? null : ['id' => $client->id, 'name' => $client->name],
            'currency' => $this->packages->currency(),
            'packages' => $this->packages->all(),
            'limits' => $this->packages->limits(),
            'gateway' => [
                'driver' => (string) config('billing.driver', 'paystack'),
                'live' => $this->gateways->isLive(),
                'mobile_money' => $this->gateways->supportsMobileMoney(),
            ],
            'payments' => $client === null ? [] : $this->recentPayments($client),
            'invoices' => $client === null ? [] : $this->recentInvoices($client),
        ]);
    }

    /**
     * The last few top ups started for a client.
     *
     * @return list<array<string, mixed>>
     */
    protected function recentPayments(Client $client): array
    {
        return Payment::query()
            ->where('client_id', $client->id)
            ->latest()
            ->take(5)
            ->get()
            ->map(fn (Payment $payment): array => [
                'id' => $payment->id,
                'reference' => $payment->reference,
                'description' => $payment->description,
                'status' => $payment->status->value,
                'status_label' => $payment->status->label(),
                'currency' => $payment->currency,
                'amount' => (float) $payment->amount,
                'channel' => $payment->channel,
                'failure_reason' => $payment->failure_reason,
                'authorization_url' => $payment->status->isOpen() ? $payment->authorization_url : null,
                'cancel_url' => $payment->status->isOpen() ? route('wallet.checkout.cancel', $payment) : null,
                'created_at' => $payment->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * The last few receipts raised for a client.
     *
     * @return list<array<string, mixed>>
     */
    protected function recentInvoices(Client $client): array
    {
        return Invoice::query()
            ->where('client_id', $client->id)
            ->latest('issued_at')
            ->take(5)
            ->get()
            ->map(fn (Invoice $invoice): array => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'status' => $invoice->status->value,
                'status_label' => $invoice->status->label(),
                'currency' => $invoice->currency,
                'total' => (float) $invoice->total,
                'issued_at' => $invoice->issued_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * Credit the client wallet.
     */
    public function topUp(TopUpWalletRequest $request): RedirectResponse
    {
        $client = $this->tenantClient($request);

        abort_if($client === null, 403, 'Select a client account before topping up.');

        abort_unless(
            $request->user()->isPlatformUser(),
            403,
            'Wallets are funded by card. Only the platform team may credit a wallet directly.',
        );

        $wallet = $this->wallets->walletFor($client);

        $this->wallets->topUp(
            $wallet,
            (float) $request->float('amount'),
            $request->user(),
            $request->string('description')->toString() ?: 'Wallet top up',
            $request->string('reference')->toString() ?: null,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Wallet credited with GHS '.number_format($request->float('amount'), 2).'.',
        ]);
    }
}
