<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Enums\ReservationStatus;
use App\Http\Controllers\Concerns\ResolvesTenantClient;
use App\Http\Requests\Wallet\TopUpWalletRequest;
use App\Models\BillingReservation;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class WalletController extends Controller
{
    use ResolvesTenantClient;

    public function __construct(protected WalletService $wallets) {}

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
                'top_up' => $request->user()->can(Permission::TopUpWallet->value),
            ],
            'clients' => $this->selectableClients($request),
        ]);
    }

    /**
     * Credit the client wallet.
     */
    public function topUp(TopUpWalletRequest $request): RedirectResponse
    {
        $client = $this->tenantClient($request);

        abort_if($client === null, 403, 'Select a client account before topping up.');

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
