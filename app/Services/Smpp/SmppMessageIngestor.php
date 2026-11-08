<?php

namespace App\Services\Smpp;

use App\Enums\SmppMessageStatus;
use App\Jobs\DeliverSmppMessage;
use App\Models\SmppAccount;
use App\Models\SmppMessage;
use App\Models\SmppSession;
use App\Services\SmsSegmenter;
use Illuminate\Support\Str;

/**
 * Turns an accepted `submit_sm` into a message row and a queued submission.
 *
 * The ESME is answered as soon as the row exists: the response is the promise
 * that the message was accepted for delivery, and the transport is contacted on
 * the `sms` queue where a slow carrier cannot stall the server loop.
 */
class SmppMessageIngestor
{
    public function __construct(
        protected SmsSegmenter $segmenter,
        protected SmppReceiptRecorder $receipts,
    ) {}

    /**
     * Store the submission and queue it for the transport.
     */
    public function ingest(
        SmppAccount $account,
        ?SmppSession $session,
        string $source,
        string $destination,
        string $body,
    ): SmppMessage {
        $encoding = $this->segmenter->encodingFor($body);

        $message = SmppMessage::query()->create([
            'client_id' => $account->client_id,
            'smpp_account_id' => $account->id,
            'smpp_session_id' => $session?->id,
            'reference' => (string) Str::ulid(),
            'message_id' => (string) Str::ulid(),
            'source_address' => $source,
            'destination_address' => $destination,
            'body' => $body,
            'encoding' => $encoding,
            'segments' => $this->segmenter->segmentsFor($body),
            'status' => SmppMessageStatus::Accepted,
            'submitted_at' => now(),
        ]);

        $this->receipts->queue($message, SmppMessageStatus::Accepted);

        DeliverSmppMessage::dispatch($message->id)
            ->onQueue((string) config('smpp.ingest.queue', 'sms'));

        return $message;
    }

    /**
     * The source address to use when an ESME submits without one.
     */
    public function defaultSource(): string
    {
        return (string) config('smpp.ingest.default_source', 'OMNICAST');
    }
}
