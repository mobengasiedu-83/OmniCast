<?php

namespace App\Services\Reporting;

use App\Enums\Channel;
use App\Enums\EmailMessageStatus;
use App\Enums\SmsMessageStatus;
use App\Enums\VoiceCallStatus;
use App\Models\Client;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * The cross-client delivery numbers for one channel.
 *
 * The platform reads the same event tables a tenant report reads, grouped by
 * client instead of by campaign, so an operator compares accounts without
 * opening each one. Delivery is counted from what the provider confirmed, never
 * from what the dispatcher handed over, and the status lists are the ones the
 * provider performance report uses so the two screens cannot disagree.
 */
class ChannelDeliveryReport
{
    /**
     * The totals, the daily trend and the client breakdown for one channel.
     *
     * @return array<string, mixed>
     */
    public function for(Channel $channel, ReportScope $scope, ReportPeriod $period): array
    {
        $spec = $this->spec($channel);

        /** @var callable(): Builder<*> $window */
        $window = fn (): Builder => $spec['query']($scope)
            ->whereBetween($spec['date_column'], [$period->from, $period->to]);

        return [
            'channel' => [
                'value' => $channel->value,
                'label' => $channel->label(),
                'unit' => $spec['unit'],
            ],
            'totals' => $this->totals($window(), $spec),
            'daily' => $this->daily($window(), $spec),
            'byClient' => $this->byClient($window(), $spec),
        ];
    }

    /**
     * How the channel performed over the whole window.
     *
     * @param  Builder<*>  $query
     * @param  array<string, mixed>  $spec
     * @return array<string, mixed>
     */
    protected function totals(Builder $query, array $spec): array
    {
        $counts = (clone $query)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $total = (int) $counts->sum();
        $delivered = $this->count($counts, $spec['delivered']);
        $failed = $this->count($counts, $spec['failed']);

        return [
            'total' => $total,
            'delivered' => $delivered,
            'failed' => $failed,
            'pending' => max($total - $delivered - $failed, 0),
            'delivery_rate' => $total === 0 ? null : round($delivered / $total * 100, 1),
            'spend' => round((float) (clone $query)->sum('cost'), 2),
            'clients' => (clone $query)->distinct()->count('client_id'),
        ];
    }

    /**
     * One point per day the channel carried traffic.
     *
     * @param  Builder<*>  $query
     * @param  array<string, mixed>  $spec
     * @return list<array<string, mixed>>
     */
    protected function daily(Builder $query, array $spec): array
    {
        $column = $spec['date_column'];
        $delivered = $this->placeholders($spec['delivered']);
        $failed = $this->placeholders($spec['failed']);

        return (clone $query)
            ->selectRaw(
                "date({$column}) as date, count(*) as total, "
                ."sum(case when status in ({$delivered}) then 1 else 0 end) as delivered, "
                ."sum(case when status in ({$failed}) then 1 else 0 end) as failed, "
                .'coalesce(sum(cost), 0) as spend',
                [...$spec['delivered'], ...$spec['failed']],
            )
            ->groupByRaw("date({$column})")
            ->orderBy('date')
            ->limit(120)
            ->get()
            ->map(fn ($row): array => [
                'date' => (string) $row->date,
                'label' => Carbon::parse((string) $row->date)->format('j M'),
                'total' => (int) $row->total,
                'delivered' => (int) $row->delivered,
                'failed' => (int) $row->failed,
                'spend' => round((float) $row->spend, 2),
            ])
            ->values()
            ->all();
    }

    /**
     * The clients that pushed the most volume through the channel.
     *
     * @param  Builder<*>  $query
     * @param  array<string, mixed>  $spec
     * @return list<array<string, mixed>>
     */
    protected function byClient(Builder $query, array $spec): array
    {
        $delivered = $this->placeholders($spec['delivered']);
        $failed = $this->placeholders($spec['failed']);

        $rows = (clone $query)
            ->selectRaw(
                'client_id, count(*) as total, '
                ."sum(case when status in ({$delivered}) then 1 else 0 end) as delivered, "
                ."sum(case when status in ({$failed}) then 1 else 0 end) as failed, "
                .'coalesce(sum(cost), 0) as spend',
                [...$spec['delivered'], ...$spec['failed']],
            )
            ->groupBy('client_id')
            ->orderByDesc('total')
            ->limit(25)
            ->get();

        $names = Client::query()
            ->whereIn('id', $rows->pluck('client_id')->all())
            ->pluck('name', 'id');

        return $rows
            ->map(function ($row) use ($names): array {
                $total = (int) $row->total;
                $delivered = (int) $row->delivered;
                $failed = (int) $row->failed;

                return [
                    'client_id' => (int) $row->client_id,
                    'name' => (string) $names->get($row->client_id, 'Unknown client'),
                    'total' => $total,
                    'delivered' => $delivered,
                    'failed' => $failed,
                    'pending' => max($total - $delivered - $failed, 0),
                    'delivery_rate' => $total === 0 ? null : round($delivered / $total * 100, 1),
                    'spend' => round((float) $row->spend, 2),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Which table, date column and outcome statuses a channel is counted from.
     *
     * @return array{unit: string, date_column: string, query: callable(ReportScope): Builder<*>, delivered: list<string>, failed: list<string>}
     */
    protected function spec(Channel $channel): array
    {
        return match ($channel) {
            Channel::Sms => [
                'unit' => 'Messages',
                'date_column' => 'created_at',
                'query' => fn (ReportScope $scope): Builder => $scope->sms(),
                'delivered' => [SmsMessageStatus::Delivered->value],
                'failed' => [
                    SmsMessageStatus::Undelivered->value,
                    SmsMessageStatus::Expired->value,
                    SmsMessageStatus::Rejected->value,
                    SmsMessageStatus::Failed->value,
                ],
            ],
            Channel::Voice => [
                'unit' => 'Calls',
                'date_column' => 'dialled_at',
                'query' => fn (ReportScope $scope): Builder => $scope->voice(),
                'delivered' => [
                    VoiceCallStatus::Answered->value,
                    VoiceCallStatus::Completed->value,
                ],
                'failed' => [
                    VoiceCallStatus::NoAnswer->value,
                    VoiceCallStatus::Busy->value,
                    VoiceCallStatus::Failed->value,
                    VoiceCallStatus::Canceled->value,
                ],
            ],
            Channel::Email => [
                'unit' => 'Emails',
                'date_column' => 'created_at',
                'query' => fn (ReportScope $scope): Builder => $scope->email(),
                'delivered' => [EmailMessageStatus::Delivered->value],
                'failed' => [
                    EmailMessageStatus::Bounced->value,
                    EmailMessageStatus::Complained->value,
                    EmailMessageStatus::Rejected->value,
                    EmailMessageStatus::Failed->value,
                ],
            ],
            Channel::WhatsApp => throw new InvalidArgumentException('WhatsApp reporting is not available until delivery evidence is implemented.'),
            default => throw new InvalidArgumentException("{$channel->value} is not a delivery channel."),
        };
    }

    /**
     * Bind a status list into `in (...)` placeholders.
     *
     * @param  list<string>  $statuses
     */
    protected function placeholders(array $statuses): string
    {
        $statuses = $statuses === [] ? ['__none__'] : $statuses;

        return implode(', ', array_fill(0, count($statuses), '?'));
    }

    /**
     * Count the rows a status list accounts for.
     *
     * @param  Collection<string, int|string>  $counts
     * @param  list<string>  $statuses
     */
    protected function count(Collection $counts, array $statuses): int
    {
        $total = 0;

        foreach ($statuses as $status) {
            $total += (int) $counts->get($status, 0);
        }

        return $total;
    }
}
