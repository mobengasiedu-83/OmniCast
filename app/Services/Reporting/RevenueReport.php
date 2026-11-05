<?php

namespace App\Services\Reporting;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\WalletTransactionType;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Builder;

/**
 * The money the platform took in and the credit it holds for clients.
 *
 * Revenue is what the gateway settled (`paid_at` inside the window), which is
 * the only figure the ledger can prove; a requested payment that never settled
 * is reported separately so it is never counted as income.
 */
class RevenueReport
{
    /**
     * @return array<string, mixed>
     */
    public function for(ReportScope $scope, ReportPeriod $period): array
    {
        $payments = $scope->payments()->whereBetween('created_at', [$period->from, $period->to]);
        $settled = $scope->payments()
            ->where('status', PaymentStatus::Paid->value)
            ->whereBetween('paid_at', [$period->from, $period->to]);

        $invoices = $scope->invoices()->whereBetween('issued_at', [$period->from, $period->to]);
        $wallet = $scope->walletTransactions()->whereBetween('created_at', [$period->from, $period->to]);
        $campaigns = $scope->campaigns()->whereBetween('created_at', [$period->from, $period->to]);

        $balances = $scope->wallets()
            ->selectRaw('coalesce(sum(balance), 0) as balance, coalesce(sum(reserved_balance), 0) as reserved')
            ->first();

        return [
            'totals' => [
                'revenue' => round((float) (clone $settled)->sum('amount_paid'), 2),
                'payments' => (clone $settled)->count(),
                'pending_payments' => (clone $payments)->where('status', PaymentStatus::Pending->value)->count(),
                'failed_payments' => (clone $payments)->where('status', PaymentStatus::Failed->value)->count(),
                'invoiced' => round((float) (clone $invoices)->sum('total'), 2),
                'invoices' => (clone $invoices)->count(),
                'spend' => round((float) (clone $campaigns)->sum('actual_cost'), 2),
                'balance' => round((float) ($balances->balance ?? 0), 2),
                'reserved' => round((float) ($balances->reserved ?? 0), 2),
            ],
            'paymentStatuses' => $this->paymentStatuses($payments),
            'invoiceStatuses' => $this->invoiceStatuses($invoices),
            'wallet' => $this->walletEntries($wallet),
            'byProvider' => (clone $settled)
                ->selectRaw('provider, count(*) as payments, coalesce(sum(amount_paid), 0) as revenue')
                ->groupBy('provider')
                ->orderByDesc('revenue')
                ->get()
                ->map(fn ($row): array => [
                    'provider' => (string) $row->provider,
                    'payments' => (int) $row->payments,
                    'revenue' => round((float) $row->revenue, 2),
                ])
                ->values()
                ->all(),
            'monthly' => $this->monthly($scope),
        ];
    }

    /**
     * @param  Builder<Payment>  $payments
     * @return list<array{status: string, label: string, total: int, amount: float}>
     */
    protected function paymentStatuses($payments): array
    {
        $totals = (clone $payments)
            ->selectRaw('status, count(*) as total, coalesce(sum(amount), 0) as amount')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        return collect(PaymentStatus::cases())
            ->map(fn (PaymentStatus $status): array => [
                'status' => $status->value,
                'label' => $status->label(),
                'total' => (int) ($totals->get($status->value)->total ?? 0),
                'amount' => round((float) ($totals->get($status->value)->amount ?? 0), 2),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Builder<Invoice>  $invoices
     * @return list<array{status: string, label: string, total: int, amount: float}>
     */
    protected function invoiceStatuses($invoices): array
    {
        $totals = (clone $invoices)
            ->selectRaw('status, count(*) as total, coalesce(sum(total), 0) as amount')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        return collect(InvoiceStatus::cases())
            ->map(fn (InvoiceStatus $status): array => [
                'status' => $status->value,
                'label' => $status->label(),
                'total' => (int) ($totals->get($status->value)->total ?? 0),
                'amount' => round((float) ($totals->get($status->value)->amount ?? 0), 2),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Builder<WalletTransaction>  $wallet
     * @return list<array{type: string, label: string, entries: int, amount: float}>
     */
    protected function walletEntries($wallet): array
    {
        $totals = (clone $wallet)
            ->selectRaw('type, count(*) as entries, coalesce(sum(amount), 0) as amount')
            ->groupBy('type')
            ->get()
            ->keyBy('type');

        return collect(WalletTransactionType::cases())
            ->map(fn (WalletTransactionType $type): array => [
                'type' => $type->value,
                'label' => $type->label(),
                'entries' => (int) ($totals->get($type->value)->entries ?? 0),
                'amount' => round((float) ($totals->get($type->value)->amount ?? 0), 2),
            ])
            ->values()
            ->all();
    }

    /**
     * The last six months of settled revenue and campaign spend.
     *
     * @return list<array<string, mixed>>
     */
    protected function monthly(ReportScope $scope): array
    {
        return collect(range(5, 0))
            ->map(function (int $monthsAgo) use ($scope): array {
                $month = now()->subMonthsNoOverflow($monthsAgo);

                $revenue = $scope->payments()
                    ->where('status', PaymentStatus::Paid->value)
                    ->whereYear('paid_at', $month->year)
                    ->whereMonth('paid_at', $month->month);

                $spend = $scope->campaigns()
                    ->whereYear('created_at', $month->year)
                    ->whereMonth('created_at', $month->month);

                return [
                    'month' => $month->format('Y-m'),
                    'label' => $month->format('M Y'),
                    'revenue' => round((float) (clone $revenue)->sum('amount_paid'), 2),
                    'spend' => round((float) (clone $spend)->sum('actual_cost'), 2),
                ];
            })
            ->values()
            ->all();
    }
}
