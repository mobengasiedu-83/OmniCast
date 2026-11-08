<?php

namespace App\Services\Smpp;

use App\Enums\SmppMessageStatus;
use App\Enums\WebhookEvent;
use App\Models\SmppDelivery;
use App\Models\SmppMessage;
use App\Services\Sms\SmsDeliveryReport;
use App\Services\Webhooks\WebhookDispatcher;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;

/**
 * Queues the `deliver_sm` receipts an ESME is owed and applies incoming ones.
 *
 * The daemon that owns the sockets and the worker that submits the message are
 * different processes, so a receipt is written as a row and handed to whichever
 * session is bound the next time the server loop runs.
 */
class SmppReceiptRecorder
{
    public function __construct(protected WebhookDispatcher $webhooks) {}

    /**
     * Queue a receipt for an ESME to collect.
     *
     * A message submitted by an account that has since been deleted has nobody
     * to report to, so the receipt is not written at all.
     */
    public function queue(SmppMessage $message, SmppMessageStatus $status, ?string $error = null): ?SmppDelivery
    {
        $message->loadMissing('account');
        $account = $message->account;

        if ($account === null) {
            return null;
        }

        return SmppDelivery::query()->create([
            'smpp_message_id' => $message->id,
            'smpp_account_id' => $account->id,
            'system_id' => $account->system_id,
            'stat' => $status->stat(),
            'err' => $error === null ? '000' : '001',
            'text' => $this->receiptText($message, $status, $error),
        ]);
    }

    /**
     * Apply a delivery receipt the network posted to the signed SMS webhook.
     *
     * Returns null when the reference is not an SMPP submission, which is what
     * keeps the campaign receipt path from having to know about SMPP at all.
     */
    public function apply(SmsDeliveryReport $report): ?SmppMessage
    {
        $message = SmppMessage::query()->where('reference', $report->reference)->first();

        if ($message === null) {
            return null;
        }

        if ($message->status->isTerminal()) {
            return $message;
        }

        $status = $this->statusFrom($report);

        $message->forceFill([
            'status' => $status,
            'provider' => $message->provider ?? $report->providerStatus,
            'provider_message_id' => $report->providerMessageId ?? $message->provider_message_id,
            'error_message' => $report->error,
            'delivered_at' => $status === SmppMessageStatus::Delivered ? ($report->occurredAt ?? now()) : null,
            'failed_at' => $status->isTerminal() && $status !== SmppMessageStatus::Delivered ? ($report->occurredAt ?? now()) : null,
        ])->save();

        $this->queue($message, $status, $report->error);

        Log::channel('sms')->info('SMPP message receipt applied.', [
            'reference' => $message->reference,
            'status' => $status->value,
        ]);

        $this->webhooks->smpp(
            $status === SmppMessageStatus::Delivered ? WebhookEvent::SmsDelivered : WebhookEvent::SmsFailed,
            $message,
        );

        return $message;
    }

    /**
     * The standard text body an SMPP delivery receipt carries.
     *
     * `submit date` and `done date` are `YYMMDDhhmm`; the text is truncated to
     * the first 20 characters, which is what the specification recommends.
     */
    public function receiptText(
        SmppMessage $message,
        SmppMessageStatus $status,
        ?string $error = null,
        ?CarbonInterface $when = null,
    ): string {
        $submitted = $message->submitted_at ?? $message->created_at;
        $done = $when ?? now();

        return sprintf(
            'id:%s sub:001 dlvrd:%s submit date:%s done date:%s stat:%s err:%s text:%s',
            $message->message_id,
            $status === SmppMessageStatus::Delivered ? '001' : '000',
            $submitted?->format('ymdHi') ?? $done->format('ymdHi'),
            $done->format('ymdHi'),
            $status->stat(),
            $error === null ? '000' : '001',
            mb_substr(preg_replace('/\s+/', ' ', $message->body) ?? '', 0, 20),
        );
    }

    /**
     * Translate the platform's SMS status onto an SMPP receipt state.
     */
    protected function statusFrom(SmsDeliveryReport $report): SmppMessageStatus
    {
        if ($report->status->wasDelivered()) {
            return SmppMessageStatus::Delivered;
        }

        return match ($report->status->value) {
            'rejected' => SmppMessageStatus::Rejected,
            'failed', 'undelivered', 'expired' => SmppMessageStatus::Failed,
            default => SmppMessageStatus::Submitted,
        };
    }
}
