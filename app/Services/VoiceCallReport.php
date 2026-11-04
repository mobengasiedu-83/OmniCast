<?php

namespace App\Services;

use App\Enums\VoiceCallEventType;
use App\Enums\VoiceCallStatus;
use App\Enums\VoiceIvrAction;
use App\Models\Campaign;
use App\Models\VoiceCall;
use App\Models\VoiceCallEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Aggregates the call log into the numbers a call report is made of.
 *
 * Answer rate counts calls a human (or a machine) picked up, talk time only
 * covers those calls, and the DTMF breakdown is read from the events so a menu
 * can be tuned from what recipients actually pressed.
 */
class VoiceCallReport
{
    /**
     * The call report for a single campaign.
     *
     * @return array<string, mixed>
     */
    public function forCampaign(Campaign $campaign, int $limit = 10): array
    {
        return $this->summarise(
            VoiceCall::query()->where('campaign_id', $campaign->id),
            $limit,
        );
    }

    /**
     * The call report for an arbitrary call query (already scoped to a tenant).
     *
     * @return array<string, mixed>
     */
    public function forQuery(Builder $query, int $limit = 10): array
    {
        return $this->summarise($query, $limit);
    }

    /**
     * @return array<string, mixed>
     */
    protected function summarise(Builder $query, int $limit): array
    {
        $counts = (clone $query)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $calls = (int) $counts->sum();
        $answered = $this->count($counts, VoiceCallStatus::Answered) + $this->count($counts, VoiceCallStatus::Completed);
        $noAnswer = $this->count($counts, VoiceCallStatus::NoAnswer);
        $busy = $this->count($counts, VoiceCallStatus::Busy);
        $failed = $this->count($counts, VoiceCallStatus::Failed);
        $canceled = $this->count($counts, VoiceCallStatus::Canceled);

        $averageDuration = $answered === 0
            ? 0
            : (int) round((float) (clone $query)->answered()->avg('duration_seconds'));

        return [
            'totals' => [
                'calls' => $calls,
                'answered' => $answered,
                'no_answer' => $noAnswer,
                'busy' => $busy,
                'failed' => $failed,
                'canceled' => $canceled,
                'answer_rate' => $calls === 0 ? null : round($answered / $calls * 100, 1),
                'average_duration' => $averageDuration,
                'opt_outs' => (clone $query)->where('ivr_action', VoiceIvrAction::OptOut->value)->count(),
                'spend' => round((float) (clone $query)->sum('cost'), 2),
            ],
            'digits' => $this->digits($query),
            'recent' => $this->recent($query, $limit),
        ];
    }

    /**
     * How often each key of the menu was pressed.
     *
     * @return list<array{digit: string|null, action: string|null, action_label: string|null, total: int}>
     */
    protected function digits(Builder $query): array
    {
        return VoiceCallEvent::query()
            ->where('type', VoiceCallEventType::Dtmf->value)
            ->whereIn('voice_call_id', (clone $query)->select('voice_calls.id'))
            ->selectRaw('digit, ivr_action, count(*) as total')
            ->groupBy('digit', 'ivr_action')
            ->orderByDesc('total')
            ->get()
            ->map(fn (VoiceCallEvent $event): array => [
                'digit' => $event->digit,
                'action' => $event->ivr_action,
                'action_label' => $event->ivr_action === null
                    ? null
                    : VoiceIvrAction::tryFrom((string) $event->ivr_action)?->label(),
                'total' => (int) $event->getAttribute('total'),
            ])
            ->all();
    }

    /**
     * The latest calls, with the recipient they were dialled for.
     *
     * @return list<array<string, mixed>>
     */
    protected function recent(Builder $query, int $limit): array
    {
        return (clone $query)
            ->with('campaignContact.contact:id,first_name,last_name,phone')
            ->latest('voice_calls.id')
            ->limit($limit)
            ->get()
            ->map(fn (VoiceCall $call): array => [
                'id' => $call->id,
                'reference' => $call->reference,
                'to' => $call->to_number,
                'from' => $call->from_number,
                'contact' => $call->campaignContact?->contact?->full_name,
                'status' => $call->status->value,
                'status_label' => $call->status->label(),
                'attempts' => $call->attempts,
                'duration_seconds' => $call->duration_seconds,
                'dtmf_digits' => $call->dtmf_digits,
                'ivr_action' => $call->ivr_action,
                'ivr_action_label' => $call->ivr_action === null
                    ? null
                    : VoiceIvrAction::tryFrom((string) $call->ivr_action)?->label(),
                'dialled_at' => $call->dialled_at?->toIso8601String(),
                'ended_at' => $call->ended_at?->toIso8601String(),
                'error_message' => $call->error_message,
            ])
            ->all();
    }

    /**
     * @param  Collection<string, int|string>  $counts
     */
    protected function count(Collection $counts, VoiceCallStatus $status): int
    {
        return (int) $counts->get($status->value, 0);
    }
}
