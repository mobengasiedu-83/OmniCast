<?php

namespace App\Services;

use App\Enums\SmsMessageStatus;
use App\Models\Campaign;
use App\Models\SmsMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Aggregates the message log into the numbers a delivery report is made of.
 *
 * The delivery rate counts messages the handset confirmed, which only moves
 * once the network posts a receipt; `pending` is everything the network has not
 * answered for yet, so a report never claims delivery it cannot prove.
 */
class SmsMessageReport
{
    /**
     * The delivery report for a single campaign.
     *
     * @return array<string, mixed>
     */
    public function forCampaign(Campaign $campaign, int $limit = 10): array
    {
        return $this->summarise(
            SmsMessage::query()->where('campaign_id', $campaign->id),
            $limit,
        );
    }

    /**
     * The delivery report for an arbitrary message query (already scoped to a tenant).
     *
     * @param  Builder<SmsMessage>  $query
     * @return array<string, mixed>
     */
    public function forQuery(Builder $query, int $limit = 10): array
    {
        return $this->summarise($query, $limit);
    }

    /**
     * @param  Builder<SmsMessage>  $query
     * @return array<string, mixed>
     */
    protected function summarise(Builder $query, int $limit): array
    {
        $counts = (clone $query)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $messages = (int) $counts->sum();
        $delivered = $this->count($counts, SmsMessageStatus::Delivered);
        $undelivered = $this->count($counts, SmsMessageStatus::Undelivered);
        $expired = $this->count($counts, SmsMessageStatus::Expired);
        $rejected = $this->count($counts, SmsMessageStatus::Rejected);
        $failed = $this->count($counts, SmsMessageStatus::Failed);
        $pending = $this->count($counts, SmsMessageStatus::Queued) + $this->count($counts, SmsMessageStatus::Submitted);

        return [
            'totals' => [
                'messages' => $messages,
                'delivered' => $delivered,
                'pending' => $pending,
                'undelivered' => $undelivered,
                'expired' => $expired,
                'rejected' => $rejected,
                'failed' => $failed,
                'delivery_rate' => $messages === 0 ? null : round($delivered / $messages * 100, 1),
                'segments' => (int) (clone $query)->sum('segments'),
                'spend' => round((float) (clone $query)->sum('cost'), 2),
            ],
            'recent' => $this->recent($query, $limit),
        ];
    }

    /**
     * The latest messages, with the recipient they were submitted for.
     *
     * @param  Builder<SmsMessage>  $query
     * @return list<array<string, mixed>>
     */
    protected function recent(Builder $query, int $limit): array
    {
        $rows = (clone $query)
            ->with('campaignContact.contact:id,first_name,last_name,phone')
            ->latest('sms_messages.id')
            ->limit($limit)
            ->get()
            ->map(fn (SmsMessage $message): array => [
                'id' => $message->id,
                'reference' => $message->reference,
                'to' => $message->to_number,
                'from' => $message->from_number,
                'contact' => $message->campaignContact?->contact?->full_name,
                'status' => $message->status->value,
                'status_label' => $message->status->label(),
                'provider_status' => $message->provider_status,
                'segments' => $message->segments,
                'attempts' => $message->attempts,
                'submitted_at' => $message->submitted_at?->toIso8601String(),
                'delivered_at' => $message->delivered_at?->toIso8601String(),
                'error_message' => $message->error_message,
            ])
            ->values()
            ->all();

        return array_values($rows);
    }

    /**
     * @param  Collection<string, int|string>  $counts
     */
    protected function count(Collection $counts, SmsMessageStatus $status): int
    {
        return (int) $counts->get($status->value, 0);
    }
}
