<?php

namespace App\Services\WhatsApp;

use App\Contracts\WhatsApp\WhatsAppProvider as WhatsAppProviderContract;
use App\Enums\WhatsAppApprovalStatus;
use App\Enums\WhatsAppProviderErrorCategory;
use App\Enums\WhatsAppProviderResultStatus;
use App\Exceptions\InsufficientWalletBalance;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppCampaignRecipient;
use App\Models\WhatsAppMessageAttempt;
use App\Models\WhatsAppProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WhatsAppDispatcher
{
    public function __construct(
        private WhatsAppProviderContract $provider,
        private WhatsAppTemplatePayloadValidator $validator,
        private WhatsAppCampaignBuilder $campaigns,
        private WhatsAppRateLimiter $limiter,
        private WhatsAppDispatchMetrics $metrics,
        private WhatsAppWalletService $wallet,
    ) {}

    public function prepare(WhatsAppCampaign $campaign, bool $test = false): void
    {
        if (! config('whatsapp.sending_enabled', false)) {
            $campaign->forceFill(['status' => 'blocked', 'control_state' => 'blocked'])->save();

            return;
        }
        $campaign->loadMissing(['connector.provider', 'template']);
        if ($campaign->status === 'preparing' && $campaign->dispatch_started_at !== null && $campaign->dispatch_started_at->isBefore(now()->subMinutes(1))) {
            $campaign->forceFill(['status' => 'failed', 'control_state' => 'failed'])->save();
            $this->metrics->record('stalled_preparation');

            return;
        }
        $assignment = $campaign->connector->clientAssignments()->where('client_id', $campaign->client_id)
            ->where('enabled', true)->where('approval_status', WhatsAppApprovalStatus::Approved)->exists();
        if (! $assignment || ! $campaign->connector->provider->is_enabled || $campaign->connector->status->value !== 'available' || ! $campaign->template->isApproved()) {
            $campaign->forceFill(['status' => 'blocked', 'control_state' => 'blocked'])->save();

            return;
        }
        if ($test && $campaign->recipients()->where('eligibility', 'eligible')->count() > (int) config('whatsapp.dispatch.test_recipient_limit', 1)) {
            $campaign->forceFill(['status' => 'blocked', 'control_state' => 'blocked'])->save();

            return;
        }
        if ($campaign->control_state === 'paused' || $campaign->control_state === 'cancelled') {
            return;
        }
        $eligibleCount = $this->campaigns->refreshEligibility($campaign);
        if ($eligibleCount === 0) {
            $this->wallet->releaseIfNoBillableOutcomes($campaign, 'No eligible recipients remained at dispatch time.');
            $hasUnpriced = $campaign->recipients()->get()->contains(fn (WhatsAppCampaignRecipient $recipient): bool => collect($recipient->errors_json)->contains(fn (string $error): bool => str_contains($error, 'rate')));
            $campaign->forceFill($hasUnpriced
                ? ['status' => 'blocked', 'control_state' => 'blocked']
                : ['status' => 'completed', 'control_state' => 'completed', 'completed_at' => now()])->save();

            return;
        }
        $estimate = (float) $campaign->recipients()->where('status', 'ready')->sum('unit_price');
        $campaign->forceFill(['estimated_recipients' => $eligibleCount, 'estimated_cost' => number_format($estimate, 4, '.', '')])->save();
        try {
            $this->wallet->reserve($campaign->fresh());
        } catch (InsufficientWalletBalance) {
            $campaign->forceFill(['status' => 'blocked', 'control_state' => 'blocked'])->save();
            $this->metrics->record('insufficient_wallet');

            return;
        }
        $campaign->forceFill([
            'status' => 'sending',
            'control_state' => 'active',
            'dispatch_started_at' => $campaign->dispatch_started_at ?? now(),
        ])->save();
        $this->dispatchNextBatch($campaign->fresh());
    }

    public function dispatchNextBatch(WhatsAppCampaign $campaign): void
    {
        if ($campaign->control_state !== 'active' || in_array($campaign->status, ['paused', 'cancelled', 'completed'], true)) {
            return;
        }
        $batchSize = min(max((int) config('whatsapp.dispatch.batch_size', 25), 1), 100);
        $ids = $campaign->recipients()->whereIn('status', ['ready', 'pending'])
            ->orderBy('id')->limit($batchSize)->pluck('id');
        if ($ids->isEmpty()) {
            $remaining = $campaign->recipients()->whereIn('status', ['submitting', 'accepted', 'unknown', 'retry_wait'])->exists();
            if (! $remaining) {
                $campaign->forceFill(['status' => 'completed', 'control_state' => 'completed', 'completed_at' => now()])->save();
            }

            return;
        }
        SendWhatsAppBatch::dispatch((int) $campaign->id, $ids->map(fn ($id): int => (int) $id)->all())
            ->onQueue((string) config('queues.channels.whatsapp.queue', 'whatsapp'));
    }

    public function dispatchDueRetries(): void
    {
        WhatsAppCampaignRecipient::query()->with('campaign')
            ->where('status', 'retry_wait')->whereNotNull('next_attempt_at')->where('next_attempt_at', '<=', now())
            ->orderBy('next_attempt_at')->limit(100)->get()->each(function (WhatsAppCampaignRecipient $recipient): void {
                if ($recipient->campaign?->control_state !== 'active') {
                    return;
                }
                RetryWhatsAppRecipient::dispatch((int) $recipient->id)->onQueue((string) config('queues.channels.whatsapp.queue', 'whatsapp'));
            });
    }

    /** @param list<int> $recipientIds */
    public function sendBatch(int $campaignId, array $recipientIds): void
    {
        $campaign = WhatsAppCampaign::query()->with(['connector.provider', 'template'])->find($campaignId);
        if (! $campaign || $campaign->control_state !== 'active') {
            return;
        }
        $provider = $campaign->connector->provider;
        $rows = WhatsAppCampaignRecipient::query()->where('campaign_id', $campaignId)->whereIn('id', $recipientIds)
            ->whereIn('status', ['ready', 'pending', 'retry_wait'])->orderBy('id')->get();
        foreach ($rows as $recipient) {
            if ($campaign->fresh()->control_state !== 'active') {
                return;
            }
            $this->submitRecipients($campaign, $provider, [$recipient]);
        }
        $this->dispatchNextBatch($campaign->fresh());
    }

    /** @param list<WhatsAppCampaignRecipient> $recipients */
    private function submitRecipients(WhatsAppCampaign $campaign, WhatsAppProvider $provider, array $recipients): void
    {
        $now = now();
        $eligibleRecipients = [];
        foreach ($recipients as $recipient) {
            if ($campaign->fresh()->control_state !== 'active') {
                return;
            }
            $reason = $this->campaigns->currentIneligibility($campaign, $recipient->consent_id, $recipient->contact, $recipient->phone, $campaign->template->category);
            if ($reason !== null) {
                $recipient->forceFill(['status' => 'ineligible', 'last_error' => $reason, 'consent_checked_at' => now()])->save();
                $this->metrics->record('suppressed_or_ineligible');

                continue;
            }
            $eligibleRecipients[] = $recipient->fresh();
            if ($recipient->attempt_count > 0 && ! in_array($recipient->status, ['retry_wait'], true)) {
                continue;
            }
        }
        $recipients = array_values(array_filter($eligibleRecipients, fn (WhatsAppCampaignRecipient $r): bool => in_array($r->status, ['ready', 'pending', 'retry_wait'], true)));
        if ($recipients === []) {
            return;
        }
        $rateKey = 'wa-provider-'.$provider->id;
        if (! $this->limiter->acquire($rateKey, (int) $campaign->connector_id, (int) $campaign->client_id)) {
            $this->metrics->record('rate_limited');
            $nextId = (int) $recipients[0]->id;
            SendWhatsAppBatch::dispatch((int) $campaign->id, [$nextId])
                ->delay(now()->addSecond())->onQueue((string) config('queues.channels.whatsapp.queue', 'whatsapp'));

            return;
        }
        $payload = $this->payload($campaign, $recipients);
        $errors = $this->validator->validate($campaign->template, $payload);
        if ($errors !== []) {
            foreach ($recipients as $recipient) {
                $recipient->forceFill(['status' => 'failed', 'failed_at' => now(), 'last_error_category' => 'validation', 'last_error' => 'Template payload failed validation.'])->save();
            }
            $this->dispatchNextBatch($campaign->fresh());

            return;
        }
        $attempts = [];
        DB::transaction(function () use ($recipients, $payload, $now, &$attempts): void {
            foreach ($recipients as $recipient) {
                $locked = WhatsAppCampaignRecipient::query()->lockForUpdate()->find($recipient->id);
                if ($locked === null || ! in_array($locked->status, ['ready', 'pending', 'retry_wait'], true)
                    || $locked->attempts()->where('status', 'unknown')->exists()) {
                    continue;
                }
                $attemptNumber = $locked->attempt_count + 1;
                $attempts[$locked->id] = WhatsAppMessageAttempt::query()->create([
                    'recipient_id' => $locked->id,
                    'attempt_number' => $attemptNumber,
                    'correlation_id' => (string) Str::uuid(),
                    'status' => 'submitting',
                    'request_payload_redacted' => $this->redact($payload),
                    'started_at' => $now,
                ]);
                $locked->forceFill(['status' => 'submitting', 'attempt_count' => $attemptNumber, 'submitted_at' => $now])->save();
            }
        });
        if ($attempts === []) {
            $this->dispatchNextBatch($campaign->fresh());

            return;
        }
        $recipients = array_values(array_filter($recipients, fn (WhatsAppCampaignRecipient $recipient): bool => isset($attempts[$recipient->id])));
        $started = microtime(true);
        try {
            $response = $this->provider->sendBatch($provider, $payload);
        } catch (\Throwable $exception) {
            report($exception);
            $this->recordUnknown($recipients, $attempts, $exception->getMessage(), null, null, $started);
            $this->finishCampaignIfDrained($campaign->fresh());
            $this->dispatchNextBatch($campaign->fresh());

            return;
        }
        $latency = (int) ((microtime(true) - $started) * 1000);
        $this->metrics->record('api_latency_ms_sum', $latency);
        $this->metrics->record('api_latency_count');
        $messageRefs = $response->messageReferences;
        if ($messageRefs === [] && $response->providerReference !== null && count($attempts) === 1) {
            $messageRefs[$this->recipientPhone($recipients, (int) array_key_first($attempts))] = $response->providerReference;
        }
        if ($response->status === WhatsAppProviderResultStatus::Accepted && count($messageRefs) === count($attempts)) {
            foreach ($attempts as $recipientId => $attempt) {
                $reference = $messageRefs[$this->recipientPhone($recipients, (int) $recipientId)] ?? null;
                if ($reference === null && count($messageRefs) === 1 && count($attempts) === 1) {
                    $reference = array_values($messageRefs)[0];
                }
                if ($reference === null) {
                    $this->markUnknown($recipientId, $attempt, 'Provider accepted batch but recipient correlation is ambiguous.', $response->httpStatus, $latency, $response->data);

                    continue;
                }
                $attempt->forceFill(['status' => 'accepted', 'provider_reference' => $reference, 'http_status' => $response->httpStatus, 'response_payload_redacted' => $response->data, 'latency_ms' => $latency, 'finished_at' => now()])->save();
                WhatsAppCampaignRecipient::query()->whereKey($recipientId)->update(['status' => 'accepted', 'provider_message_id' => $reference, 'accepted_at' => now(), 'last_error_category' => null, 'last_error' => null]);
                $this->metrics->record('accepted');
            }
            $this->finishCampaignIfDrained($campaign->fresh());
            $this->dispatchNextBatch($campaign->fresh());

            return;
        }
        if ($response->status === WhatsAppProviderResultStatus::Rejected) {
            foreach ($attempts as $recipientId => $attempt) {
                $this->handleRejected($campaign, $recipientId, $attempt, $response->httpStatus, $response->errorCategory, $response->data);
            }
            $this->finishCampaignIfDrained($campaign->fresh());
            $this->dispatchNextBatch($campaign->fresh());

            return;
        }
        $this->recordUnknown($recipients, $attempts, 'Provider outcome is ambiguous; automatic retry is disabled.', $response->httpStatus, $response->errorCategory, $started, $response->data);
        $this->finishCampaignIfDrained($campaign->fresh());
        $this->dispatchNextBatch($campaign->fresh());
    }

    private function finishCampaignIfDrained(WhatsAppCampaign $campaign): void
    {
        $open = $campaign->recipients()->whereIn('status', ['ready', 'pending', 'submitting', 'retry_wait'])->exists();
        if (! $open && $campaign->control_state === 'active') {
            $this->wallet->releaseIfNoBillableOutcomes($campaign, 'No provider-accepted or unresolved messages remain.');
            $campaign->forceFill(['status' => 'completed', 'control_state' => 'completed', 'completed_at' => now()])->save();
        }
    }

    /** @param list<WhatsAppCampaignRecipient> $recipients @return array<string, mixed> */
    private function payload(WhatsAppCampaign $campaign, array $recipients): array
    {
        $payload = ['SenderNumber' => $campaign->connector->sender_number, 'MetaTemplateId' => $campaign->template->meta_template_id];
        if ($campaign->media_asset_id !== null) {
            $asset = $campaign->mediaAsset;
            $payload['MediaFilePath'] = app(WhatsAppMediaLibrary::class)->temporaryHttpsUrl($asset);
            if ($asset->mime_type === 'application/pdf') {
                $payload['FileName'] = $asset->original_name;
            }
        }
        foreach ($recipients as $recipient) {
            $item = ['RecieverNumber' => $recipient->phone];
            if ($recipient->variables_json) {
                $item['BodyVariables'] = array_values($recipient->variables_json);
            }
            $payload['CustomPayload'][] = $item;
        }

        if (count($payload['CustomPayload'] ?? []) === 1) {
            $payload['BulkPayload'] = $payload['CustomPayload'][0];
            unset($payload['CustomPayload']);
        }

        return $payload;
    }

    /** @param list<WhatsAppCampaignRecipient> $recipients */
    private function recipientPhone(array $recipients, int $id): string
    {
        foreach ($recipients as $recipient) {
            if ((int) $recipient->id === $id) {
                return $recipient->phone;
            }
        }

        return '';
    }

    /** @param list<WhatsAppCampaignRecipient> $recipients @param array<int, WhatsAppMessageAttempt> $attempts @param array<string, mixed> $data */
    private function recordUnknown(array $recipients, array $attempts, string $message, ?int $httpStatus, ?WhatsAppProviderErrorCategory $category, float $started, array $data = []): void
    {
        $latency = (int) ((microtime(true) - $started) * 1000);
        foreach ($attempts as $recipientId => $attempt) {
            $this->markUnknown((int) $recipientId, $attempt, $message, $httpStatus, $latency, $data, $category?->value);
        }
    }

    /** @param array<string, mixed> $data */
    private function markUnknown(int $recipientId, WhatsAppMessageAttempt $attempt, string $message, ?int $httpStatus, int $latency, array $data, ?string $category = 'unknown'): void
    {
        $attempt->forceFill(['status' => 'unknown', 'http_status' => $httpStatus, 'error_category' => $category, 'error_message' => $message, 'latency_ms' => $latency, 'response_payload_redacted' => $data, 'finished_at' => now()])->save();
        WhatsAppCampaignRecipient::query()->whereKey($recipientId)->update(['status' => 'unknown', 'unknown_at' => now(), 'last_error_category' => $category, 'last_error' => $message]);
        $this->metrics->record('unknown');
    }

    /** @param array<string, mixed> $data */
    private function handleRejected(WhatsAppCampaign $campaign, int $recipientId, WhatsAppMessageAttempt $attempt, ?int $httpStatus, ?WhatsAppProviderErrorCategory $category, array $data): void
    {
        $retryable = in_array($category, [WhatsAppProviderErrorCategory::RateLimited, WhatsAppProviderErrorCategory::Provider, WhatsAppProviderErrorCategory::Transport], true);
        $maxAttempts = max((int) config('whatsapp.dispatch.max_attempts', 5), 1);
        $recipient = WhatsAppCampaignRecipient::query()->find($recipientId);
        $shouldRetry = $retryable && $recipient !== null && $recipient->attempt_count < $maxAttempts && $campaign->control_state === 'active';
        $message = 'Provider rejected message submission.';
        $attempt->forceFill(['status' => $shouldRetry ? 'retry_wait' : 'rejected', 'http_status' => $httpStatus, 'error_category' => $category?->value, 'error_message' => $message, 'response_payload_redacted' => $data, 'finished_at' => now()])->save();
        if ($recipient === null) {
            return;
        }
        if ($shouldRetry) {
            $base = max((int) config('whatsapp.dispatch.base_backoff_seconds', 30), 1);
            $cap = max((int) config('whatsapp.dispatch.max_backoff_seconds', 1800), $base);
            $seconds = min($cap, $base * (2 ** max($recipient->attempt_count - 1, 0)));
            $delay = random_int((int) floor($seconds / 2), $seconds);
            $recipient->forceFill(['status' => 'retry_wait', 'last_error_category' => $category?->value, 'last_error' => $message, 'next_attempt_at' => now()->addSeconds($delay)])->save();
            RetryWhatsAppRecipient::dispatch((int) $recipient->id)->delay(now()->addSeconds($delay))->onQueue((string) config('queues.channels.whatsapp.queue', 'whatsapp'));
        } else {
            $recipient->forceFill(['status' => 'failed', 'failed_at' => now(), 'last_error_category' => $category?->value, 'last_error' => $message])->save();
        }
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function redact(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (preg_match('/otp|password|secret|token|mediafilepath/i', (string) $key) === 1) {
                $payload[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $payload[$key] = $this->redact($value);
            }
        }

        return $payload;
    }
}
