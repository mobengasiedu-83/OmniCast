<?php

namespace App\Jobs;

use App\Contracts\SmsGateway;
use App\Enums\SmppMessageStatus;
use App\Exceptions\SmppDeliveryRejected;
use App\Models\SmppMessage;
use App\Services\Smpp\SmppReceiptRecorder;
use App\Services\Sms\OutboundSms;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Submits a message an ESME handed over on SMPP to the SMS transport.
 *
 * The message is already acknowledged to the ESME, so a transport that is down is
 * a retry rather than an error: the final failure is what queues the `UNDELIV`
 * receipt the ESME is waiting for.
 */
class DeliverSmppMessage implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $messageId) {}

    /**
     * How many times the transport is asked before the message is failed.
     */
    public function tries(): int
    {
        return max((int) config('smpp.ingest.tries', 3), 1);
    }

    /**
     * The delay between transport attempts, in seconds.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return array_values(array_map('intval', (array) config('smpp.ingest.backoff', [30, 120])));
    }

    /**
     * The number of seconds one submission may take.
     */
    public function timeout(): int
    {
        return max((int) config('smpp.ingest.timeout', 120), 1);
    }

    /**
     * Submit the message and record what the transport answered.
     */
    public function handle(SmsGateway $gateway, SmppReceiptRecorder $receipts): void
    {
        $message = SmppMessage::query()->find($this->messageId);

        if ($message === null || $message->status->isTerminal()) {
            return;
        }

        $outbound = new OutboundSms(
            reference: $message->reference,
            to: $message->destination_address,
            from: $message->source_address,
            body: $message->body,
            encoding: $message->encoding,
            segments: $message->segments,
        );

        $result = $gateway->send($outbound);

        if (! $result->accepted) {
            $this->recordAttempt($message, null, $result->message);

            if ($this->attempts() < $this->tries()) {
                throw new SmppDeliveryRejected($result->message ?? 'The SMS transport rejected the message.');
            }

            $this->fail($message, $result->message ?? 'The SMS transport rejected the message.');

            return;
        }

        $message->forceFill([
            'status' => SmppMessageStatus::Submitted,
            'provider' => $gateway->name(),
            'provider_message_id' => $result->providerMessageId,
            'error_message' => null,
            'failed_at' => null,
        ])->save();

        $this->recordAttempt($message, $result->providerMessageId, null);

        $receipts->queue($message, SmppMessageStatus::Submitted);
    }

    /**
     * Queue the final failure receipt once the transport is out of tries.
     */
    public function failed(?Throwable $exception): void
    {
        $message = SmppMessage::query()->find($this->messageId);

        if ($message === null || $message->status->isTerminal()) {
            return;
        }

        $this->fail($message, $exception?->getMessage() ?? 'The SMS transport could not be reached.');
    }

    /**
     * Keep the transport's answer on the row while the message is still being tried.
     */
    protected function recordAttempt(SmppMessage $message, ?string $providerMessageId, ?string $error): void
    {
        $message->forceFill([
            'provider_message_id' => $providerMessageId ?? $message->provider_message_id,
            'error_message' => $error,
        ])->save();
    }

    /**
     * Mark the message failed and queue the receipt the ESME is owed.
     */
    protected function fail(SmppMessage $message, string $error): void
    {
        $message->forceFill([
            'status' => SmppMessageStatus::Failed,
            'error_message' => mb_substr($error, 0, 250),
            'failed_at' => now(),
        ])->save();

        app(SmppReceiptRecorder::class)->queue($message, SmppMessageStatus::Failed, $error);
    }
}
