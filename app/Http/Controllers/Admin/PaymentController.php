<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Enums\Permission;
use App\Exceptions\PaymentGatewayException;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Payment;
use App\Services\Billing\CheckoutService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The platform view of every wallet top up: what was attempted, what the
 * gateway collected and why anything failed.
 */
class PaymentController extends Controller
{
    public function __construct(protected CheckoutService $checkout) {}

    /**
     * List the payments behind the wallet balances.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Payment::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'status' => $request->string('status')->toString(),
            'provider' => $request->string('provider')->toString(),
            'client_id' => $request->string('client_id')->toString(),
        ];

        /** @var LengthAwarePaginator<int, array<string, mixed>> $payments */
        $payments = $this->query($filters)
            ->with(['client:id,name', 'invoice:id,payment_id,number'])
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Payment $payment): array => $this->row($payment));

        return Inertia::render('admin/billing/payments', [
            'payments' => $payments,
            'filters' => $filters,
            'stats' => $this->stats($filters),
            'statuses' => $this->statusOptions(),
            'providers' => $this->providerOptions(),
            'clients' => $this->clientOptions(),
            'can' => [
                'verify' => $request->user()->can(Permission::ManageWallet->value),
            ],
        ]);
    }

    /**
     * Ask the gateway about a payment that is still open.
     */
    public function verify(Payment $payment): RedirectResponse
    {
        Gate::authorize('verify', $payment);

        try {
            $payment = $this->checkout->verify($payment);
        } catch (PaymentGatewayException $exception) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => $exception->getMessage(),
            ]);
        }

        return back()->with('toast', [
            'type' => $payment->isSettled() ? 'success' : 'warning',
            'message' => $payment->isSettled()
                ? "{$payment->reference} settled at {$payment->currency} ".number_format((float) $payment->amount_paid, 2).'.'
                : "{$payment->reference} is {$payment->status->label()}: ".($payment->failure_reason ?? 'no reason given'),
        ]);
    }

    /**
     * The filtered payment query shared by the table and the statistics.
     *
     * @param  array<string, string>  $filters
     * @return Builder<Payment>
     */
    protected function query(array $filters): Builder
    {
        return Payment::query()
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $term = '%'.$filters['search'].'%';

                $query->where(function (Builder $query) use ($term): void {
                    $query->where('reference', 'like', $term)
                        ->orWhere('description', 'like', $term)
                        ->orWhere('provider_reference', 'like', $term);
                });
            })
            ->when($filters['status'] !== '', fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($filters['provider'] !== '', fn (Builder $query) => $query->where('provider', $filters['provider']))
            ->when($filters['client_id'] !== '', fn (Builder $query) => $query->where('client_id', (int) $filters['client_id']));
    }

    /**
     * Present a payment for the table.
     *
     * @return array<string, mixed>
     */
    protected function row(Payment $payment): array
    {
        return [
            'id' => $payment->id,
            'reference' => $payment->reference,
            'client_id' => $payment->client_id,
            'client' => $payment->client?->name,
            'package_key' => $payment->package_key,
            'package' => $payment->package()['name'] ?? null,
            'description' => $payment->description,
            'provider' => $payment->provider,
            'status' => $payment->status->value,
            'status_label' => $payment->status->label(),
            'is_open' => $payment->status->isOpen(),
            'is_settled' => $payment->isSettled(),
            'currency' => $payment->currency,
            'amount' => (float) $payment->amount,
            'amount_paid' => (float) $payment->amount_paid,
            'channel' => $payment->channel,
            'failure_reason' => $payment->failure_reason,
            'invoice_number' => $payment->invoice?->number,
            'paid_at' => $payment->paid_at?->toIso8601String(),
            'created_at' => $payment->created_at?->toIso8601String(),
        ];
    }

    /**
     * The headline figures for the payments matching the filters.
     *
     * @param  array<string, string>  $filters
     * @return array{currency: string, total: int, paid: int, pending: int, failed: int, collected: float}
     */
    protected function stats(array $filters): array
    {
        $unfiltered = $filters;
        $unfiltered['status'] = '';

        return [
            'currency' => (string) config('billing.currency', 'GHS'),
            'total' => $this->query($unfiltered)->count(),
            'paid' => $this->query($unfiltered)->where('status', PaymentStatus::Paid->value)->count(),
            'pending' => $this->query($unfiltered)->where('status', PaymentStatus::Pending->value)->count(),
            'failed' => $this->query($unfiltered)->where('status', PaymentStatus::Failed->value)->count(),
            'collected' => round((float) $this->query($unfiltered)
                ->where('status', PaymentStatus::Paid->value)
                ->sum('amount_paid'), 2),
        ];
    }

    /**
     * The statuses an operator may filter on.
     *
     * @return list<array{value: string, label: string}>
     */
    protected function statusOptions(): array
    {
        return array_map(
            fn (PaymentStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
            PaymentStatus::cases(),
        );
    }

    /**
     * The gateways payments have been registered with.
     *
     * @return list<array{value: string, label: string}>
     */
    protected function providerOptions(): array
    {
        return Payment::query()
            ->distinct()
            ->orderBy('provider')
            ->pluck('provider')
            ->map(fn (string $provider): array => [
                'value' => $provider,
                'label' => ucfirst($provider),
            ])
            ->values()
            ->all();
    }

    /**
     * The client accounts an operator may filter on.
     *
     * @return list<array{id: int, name: string}>
     */
    protected function clientOptions(): array
    {
        return Client::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Client $client): array => ['id' => $client->id, 'name' => $client->name])
            ->all();
    }
}
