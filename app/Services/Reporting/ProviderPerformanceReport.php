<?php

namespace App\Services\Reporting;

use App\Enums\EmailMessageStatus;
use App\Enums\SmsMessageStatus;
use App\Enums\VoiceCallStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * How each transport behaved over the window.
 *
 * The delivery rate uses the same definition as the per-channel reports
 * (`delivered / everything sent`), so a provider that drops messages cannot
 * hide behind a denominator that excludes its failures.
 */
class ProviderPerformanceReport
{
    public function __construct(protected LedgerReconciliation $reconciliation) {}

    /**
     * @return array<string, mixed>
     */
    public function for(ReportScope $scope, ReportPeriod $period): array
    {
        return [
            'sms' => $this->channel('SMS', $this->sms($scope, $period)),
            'voice' => $this->channel('Voice', $this->voice($scope, $period)),
            'email' => $this->channel('Email', $this->email($scope, $period)),
            'reconciliation' => $this->reconciliation->for($scope, $period),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function sms(ReportScope $scope, ReportPeriod $period): array
    {
        return $this->grouped(
            $scope->sms()->whereBetween('created_at', [$period->from, $period->to]),
            [SmsMessageStatus::Delivered->value],
            [
                SmsMessageStatus::Undelivered->value,
                SmsMessageStatus::Expired->value,
                SmsMessageStatus::Rejected->value,
                SmsMessageStatus::Failed->value,
            ],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function voice(ReportScope $scope, ReportPeriod $period): array
    {
        return $this->grouped(
            $scope->voice()->whereBetween('dialled_at', [$period->from, $period->to]),
            [VoiceCallStatus::Answered->value, VoiceCallStatus::Completed->value],
            [
                VoiceCallStatus::NoAnswer->value,
                VoiceCallStatus::Busy->value,
                VoiceCallStatus::Failed->value,
                VoiceCallStatus::Canceled->value,
            ],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function email(ReportScope $scope, ReportPeriod $period): array
    {
        return $this->grouped(
            $scope->email()->whereBetween('created_at', [$period->from, $period->to]),
            [EmailMessageStatus::Delivered->value],
            [
                EmailMessageStatus::Bounced->value,
                EmailMessageStatus::Complained->value,
                EmailMessageStatus::Rejected->value,
                EmailMessageStatus::Failed->value,
            ],
        );
    }

    /**
     * Group a message log by transport and count its outcomes.
     *
     * @param  Builder<Model>  $query
     * @param  list<string>  $delivered
     * @param  list<string>  $failed
     * @return list<array<string, mixed>>
     */
    protected function grouped(Builder $query, array $delivered, array $failed): array
    {
        $deliveredPlaceholders = implode(', ', array_fill(0, count($delivered), '?'));
        $failedPlaceholders = implode(', ', array_fill(0, count($failed), '?'));

        return (clone $query)
            ->selectRaw(
                'provider, count(*) as total, '
                ."sum(case when status in ({$deliveredPlaceholders}) then 1 else 0 end) as delivered, "
                ."sum(case when status in ({$failedPlaceholders}) then 1 else 0 end) as failed, "
                .'coalesce(sum(cost), 0) as spend',
                [...$delivered, ...$failed],
            )
            ->groupBy('provider')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row): array => [
                'provider' => (string) $row->provider,
                'total' => (int) $row->total,
                'delivered' => (int) $row->delivered,
                'failed' => (int) $row->failed,
                'pending' => max((int) $row->total - (int) $row->delivered - (int) $row->failed, 0),
                'delivery_rate' => (int) $row->total > 0
                    ? round((int) $row->delivered / (int) $row->total * 100, 1)
                    : null,
                'spend' => round((float) $row->spend, 2),
            ])
            ->values()
            ->all();
    }

    /**
     * Wrap one channel's rows with their totals.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    protected function channel(string $label, array $rows): array
    {
        $total = array_sum(array_column($rows, 'total'));
        $delivered = array_sum(array_column($rows, 'delivered'));

        return [
            'label' => $label,
            'rows' => $rows,
            'totals' => [
                'total' => $total,
                'delivered' => $delivered,
                'failed' => array_sum(array_column($rows, 'failed')),
                'spend' => round(array_sum(array_column($rows, 'spend')), 2),
                'delivery_rate' => $total > 0 ? round($delivered / $total * 100, 1) : null,
            ],
        ];
    }
}
