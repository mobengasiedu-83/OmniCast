<?php

namespace App\Services;

use App\Enums\EmailMessageStatus;
use App\Models\Campaign;
use App\Models\EmailMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Aggregates the email log into the numbers a delivery report is made of.
 *
 * SMTP gives no delivery receipt of its own, so `delivery_rate` counts the
 * receipts the mail server posted back and `pending` is everything it has not
 * answered for yet: a report never claims delivery it cannot prove.
 */
class EmailMessageReport
{
    /**
     * The delivery report for a single campaign.
     *
     * @return array<string, mixed>
     */
    public function forCampaign(Campaign $campaign, int $limit = 10): array
    {
        return $this->summarise(
            EmailMessage::query()->where('campaign_id', $campaign->id),
            $limit,
        );
    }

    /**
     * The delivery report for an arbitrary message query (already scoped to a tenant).
     *
     * @param  Builder<EmailMessage>  $query
     * @return array<string, mixed>
     */
    public function forQuery(Builder $query, int $limit = 10): array
    {
        return $this->summarise($query, $limit);
    }

    /**
     * @param  Builder<EmailMessage>  $query
     * @return array<string, mixed>
     */
    protected function summarise(Builder $query, int $limit): array
    {
        $counts = (clone $query)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $messages = (int) $counts->sum();
        $delivered = $this->count($counts, EmailMessageStatus::Delivered);
        $bounced = $this->count($counts, EmailMessageStatus::Bounced);
        $complained = $this->count($counts, EmailMessageStatus::Complained);
        $rejected = $this->count($counts, EmailMessageStatus::Rejected);
        $failed = $this->count($counts, EmailMessageStatus::Failed);
        $pending = $this->count($counts, EmailMessageStatus::Queued) + $this->count($counts, EmailMessageStatus::Submitted);

        return [
            'totals' => [
                'messages' => $messages,
                'delivered' => $delivered,
                'pending' => $pending,
                'bounced' => $bounced,
                'complained' => $complained,
                'rejected' => $rejected,
                'failed' => $failed,
                'delivery_rate' => $messages === 0 ? null : round($delivered / $messages * 100, 1),
                'bounce_rate' => $messages === 0 ? null : round($bounced / $messages * 100, 1),
                'spend' => round((float) (clone $query)->sum('cost'), 2),
            ],
            'recent' => $this->recent($query, $limit),
        ];
    }

    /**
     * The latest messages, with the recipient they were submitted for.
     *
     * @param  Builder<EmailMessage>  $query
     * @return list<array<string, mixed>>
     */
    protected function recent(Builder $query, int $limit): array
    {
        $rows = (clone $query)
            ->with('campaignContact.contact:id,first_name,last_name,email')
            ->latest('email_messages.id')
            ->limit($limit)
            ->get()
            ->map(fn (EmailMessage $message): array => [
                'id' => $message->id,
                'reference' => $message->reference,
                'to' => $message->to_address,
                'from' => $message->from_address,
                'contact' => $message->campaignContact?->contact?->full_name,
                'subject' => $message->subject,
                'status' => $message->status->value,
                'status_label' => $message->status->label(),
                'provider_status' => $message->provider_status,
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
    protected function count(Collection $counts, EmailMessageStatus $status): int
    {
        return (int) $counts->get($status->value, 0);
    }
}
