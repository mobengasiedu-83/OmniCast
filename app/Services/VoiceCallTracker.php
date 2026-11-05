<?php

namespace App\Services;

use App\Enums\CampaignContactStatus;
use App\Enums\CampaignStatus;
use App\Enums\VoiceCallEventType;
use App\Enums\VoiceCallStatus;
use App\Enums\VoiceIvrAction;
use App\Jobs\DispatchCampaignJob;
use App\Models\Campaign;
use App\Models\CampaignContact;
use App\Models\VoiceCall;
use App\Services\Delivery\QueueTopology;
use App\Services\Voice\VoiceCallEventPayload;
use App\Services\Voice\VoiceDispatchResult;
use App\Services\Voice\VoiceIvrPlan;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Keeps the call log in step with what the switch reports.
 *
 * Every dial attempt writes a `voice_calls` row before the call is placed, and
 * every callback appends to its `voice_call_events`: ringing, the moment it was
 * answered, each DTMF digit and the hang-up that closes it out. A call that was
 * never answered puts its recipient back in the queue until the campaign runs
 * out of retries.
 */
class VoiceCallTracker
{
    public function __construct(protected ContactDndService $dnd, protected QueueTopology $queues) {}

    /**
     * Write the call row before the switch is asked to dial.
     */
    public function start(
        Campaign $campaign,
        CampaignContact $recipient,
        ?VoiceIvrPlan $plan,
        string $provider,
        string $from,
        string $to,
        float $cost = 0.0,
    ): VoiceCall {
        return VoiceCall::query()->create([
            'reference' => (string) Str::ulid(),
            'client_id' => $campaign->client_id,
            'campaign_id' => $campaign->id,
            'campaign_contact_id' => $recipient->id,
            'provider' => $provider,
            'to_number' => $to,
            'from_number' => $from,
            'status' => VoiceCallStatus::Queued,
            'attempts' => $recipient->voiceCalls()->count() + 1,
            'cost' => $cost,
            'last_event' => $plan === null ? null : ['ivr' => $plan->toArray()],
        ]);
    }

    /**
     * Record what the switch answered to the originate action.
     */
    public function dialled(VoiceCall $call, VoiceDispatchResult $result): void
    {
        $call->forceFill([
            'provider_call_id' => $result->providerCallId ?: $call->provider_call_id,
            'status' => $result->accepted ? VoiceCallStatus::Initiated : VoiceCallStatus::Failed,
            'dialled_at' => now(),
            'ended_at' => $result->accepted ? null : now(),
            'error_message' => $result->accepted ? null : $result->message,
        ])->save();
    }

    /**
     * Apply one callback to the call it belongs to.
     *
     * Callbacks the switch retries are de-duplicated by provider event id, and a
     * reference that is not ours is ignored rather than trusted.
     */
    public function apply(VoiceCallEventPayload $payload): ?VoiceCall
    {
        $call = VoiceCall::query()->where('reference', $payload->reference)->first();

        if ($call === null) {
            return null;
        }

        if ($payload->providerEventId !== null
            && $call->events()->where('provider_event_id', $payload->providerEventId)->exists()) {
            return $call;
        }

        $action = $payload->type === VoiceCallEventType::Dtmf
            ? $this->resolveAction($call->last_event['ivr'] ?? null, $payload->digits)
            : null;

        $call->events()->create([
            'type' => $payload->type,
            'digit' => $payload->digits,
            'ivr_action' => $action?->value,
            'provider_event_id' => $payload->providerEventId,
            'duration_seconds' => max($payload->durationSeconds, 0),
            'occurred_at' => $payload->occurredAt ?? now(),
            'payload' => $payload->payload,
        ]);

        $this->advance($call, $payload, $action);

        return $call->refresh();
    }

    /**
     * Decide what a pressed digit means for this call.
     *
     * @param  array<string, mixed>|null  $plan
     */
    protected function resolveAction(?array $plan, ?string $digits): VoiceIvrAction
    {
        if ($plan === null) {
            return VoiceIvrAction::Unrecognised;
        }

        return (new VoiceIvrPlan(
            prompt: (string) ($plan['prompt'] ?? ''),
            options: $this->mapOptions((array) ($plan['options'] ?? [])),
            timeoutSeconds: (int) ($plan['timeout_seconds'] ?? 6),
            maxAttempts: (int) ($plan['max_attempts'] ?? 3),
            optOutDigit: (string) ($plan['opt_out_digit'] ?? config('voice.ivr.opt_out_digit', '9')),
        ))->resolve($digits);
    }

    /**
     * @param  array<string, string>  $options
     * @return array<string, VoiceIvrAction>
     */
    protected function mapOptions(array $options): array
    {
        $mapped = [];

        foreach ($options as $digit => $value) {
            $action = VoiceIvrAction::fromConfig((string) $value);

            if ($action !== null) {
                $mapped[(string) $digit] = $action;
            }
        }

        return $mapped;
    }

    /**
     * Move the call forward, never backwards.
     */
    protected function advance(VoiceCall $call, VoiceCallEventPayload $payload, ?VoiceIvrAction $action): void
    {
        $now = $payload->occurredAt ?? now();

        if ($payload->type === VoiceCallEventType::Dtmf) {
            $digits = preg_replace('/\D/', '', (string) $payload->digits) ?? '';

            $call->forceFill([
                'ivr_action' => $action === VoiceIvrAction::Unrecognised ? $call->ivr_action : $action?->value,
                'dtmf_digits' => $digits === '' ? $call->dtmf_digits : mb_substr(($call->dtmf_digits ?? '').$digits, -32),
                'provider_call_id' => $payload->providerCallId ?? $call->provider_call_id,
            ])->save();

            if ($action === VoiceIvrAction::OptOut) {
                $this->optOut($call);
            }

            return;
        }

        if ($payload->type === VoiceCallEventType::Initiated) {
            $call->forceFill([
                'status' => $call->status->isTerminal() ? $call->status : VoiceCallStatus::Initiated,
                'dialled_at' => $call->dialled_at ?? $now,
                'provider_call_id' => $payload->providerCallId ?? $call->provider_call_id,
            ])->save();

            return;
        }

        if ($payload->type === VoiceCallEventType::Ringing) {
            $call->forceFill([
                'status' => $call->status->isTerminal() ? $call->status : VoiceCallStatus::Ringing,
                'ringing_at' => $call->ringing_at ?? $now,
                'provider_call_id' => $payload->providerCallId ?? $call->provider_call_id,
            ])->save();

            return;
        }

        if ($payload->type === VoiceCallEventType::Answered) {
            $call->forceFill([
                'status' => VoiceCallStatus::Answered,
                'answered_at' => $call->answered_at ?? $now,
                'provider_call_id' => $payload->providerCallId ?? $call->provider_call_id,
            ])->save();

            return;
        }

        $this->terminate($call, $payload, $now);
    }

    /**
     * Close the call out and decide whether the recipient is dialled again.
     */
    protected function terminate(VoiceCall $call, VoiceCallEventPayload $payload, CarbonInterface $endedAt): void
    {
        $status = $payload->type === VoiceCallEventType::Failed
            ? VoiceCallStatus::Failed
            : $this->finalStatus($call, $payload->hangupCause);

        $duration = $payload->durationSeconds > 0
            ? $payload->durationSeconds
            : ($call->answered_at === null ? 0 : (int) $call->answered_at->diffInSeconds($endedAt));

        $call->forceFill([
            'status' => $status,
            'ended_at' => $call->ended_at ?? $endedAt,
            'duration_seconds' => max($duration, 0),
            'hangup_cause' => $payload->hangupCause ?? $call->hangup_cause,
            'provider_call_id' => $payload->providerCallId ?? $call->provider_call_id,
            'error_message' => $status->isRetryable() ? 'The call was '.mb_strtolower($status->label()).'.' : null,
        ])->save();

        $this->settle($call->refresh());
    }

    /**
     * A finished call that nobody answered is a no-answer, not a success.
     */
    protected function finalStatus(VoiceCall $call, ?string $cause): VoiceCallStatus
    {
        $cause = mb_strtoupper(trim((string) $cause));

        $mapped = match (true) {
            in_array($cause, ['NO_ANSWER', 'NOANSWER', 'NO ANSWER'], true) => VoiceCallStatus::NoAnswer,
            in_array($cause, ['BUSY', 'USER_BUSY', 'CONGESTION'], true) => VoiceCallStatus::Busy,
            in_array($cause, ['CANCEL', 'ORIGINATOR_CANCEL'], true) => VoiceCallStatus::Canceled,
            default => null,
        };

        if ($mapped !== null) {
            return $mapped;
        }

        return $call->answered_at === null ? VoiceCallStatus::NoAnswer : VoiceCallStatus::Completed;
    }

    /**
     * Put the recipient back in the queue when the call was not answered.
     */
    protected function settle(VoiceCall $call): void
    {
        if ($call->status->wasAnswered()) {
            return;
        }

        $call->loadMissing(['campaign', 'campaignContact']);
        $campaign = $call->campaign;
        $recipient = $call->campaignContact;

        if ($campaign === null || $recipient === null) {
            return;
        }

        $attempts = max($recipient->attempts, 1);
        $exhausted = $attempts >= max($campaign->max_retries, 1);

        $recipient->forceFill([
            'status' => $exhausted ? CampaignContactStatus::Failed : CampaignContactStatus::Queued,
            'failed_at' => $exhausted ? now() : null,
            'error_message' => $exhausted
                ? "The call was not answered after {$attempts} attempts."
                : 'Waiting to redial after a '.mb_strtolower($call->status->label()).'.',
        ])->save();

        if ($exhausted || ! $call->status->isRetryable()) {
            return;
        }

        // The dispatcher closes a campaign as soon as it has dialled everyone, so
        // an unanswered call has to reopen it before the redial can be queued.
        if ($campaign->status === CampaignStatus::Completed) {
            $campaign->forceFill([
                'status' => CampaignStatus::Running,
                'completed_at' => null,
            ])->save();
        }

        DispatchCampaignJob::dispatch($campaign->id)
            ->onQueue($this->queues->queueForCampaign($campaign))
            ->delay(now()->addMinutes(max($campaign->retry_delay_minutes, 1)));
    }

    /**
     * Honour a key press that asks never to be called again.
     */
    protected function optOut(VoiceCall $call): void
    {
        $call->loadMissing('campaignContact.contact');
        $contact = $call->campaignContact?->contact;

        if ($contact === null) {
            return;
        }

        $this->dnd->apply($contact, 'voice-ivr');

        Log::info('Voice recipient opted out from the IVR', [
            'voice_call' => $call->reference,
            'contact_id' => $contact->id,
        ]);
    }
}
