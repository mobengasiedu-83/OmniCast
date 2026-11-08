<?php

use App\Enums\SmppBindMode;
use App\Enums\SmppCommand;
use App\Enums\SmppMessageStatus;
use App\Enums\SmppStatus;
use App\Enums\SmsMessageStatus;
use App\Jobs\DeliverSmppMessage;
use App\Models\SmppAccount;
use App\Models\SmppDelivery;
use App\Models\SmppMessage;
use App\Models\SmppSession;
use App\Services\Smpp\SmppConnection;
use App\Services\Smpp\SmppPdu;
use App\Services\Smpp\SmppReceiptRecorder;
use App\Services\Smpp\SmppServer;
use App\Services\Sms\SmsDeliveryReport;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();

    config()->set('smpp.enabled', true);
    config()->set('smpp.ingest.default_source', 'OMNICAST');

    [$this->client, $this->wallet, $this->user] = clientWithWallet();

    $this->account = SmppAccount::factory()->for($this->client)->create([
        'system_id' => 'ESMETEST',
        'allowed_sources' => null,
    ]);

    /**
     * Open a socket pair so the server loop talks to a real stream.
     *
     * @return array{0: SmppServer, 1: SmppConnection, 2: resource}
     */
    $this->connect = function (): array {
        [$serverSide, $clientSide] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

        stream_set_blocking($serverSide, false);
        stream_set_blocking($clientSide, false);

        $connection = new SmppConnection($serverSide, '127.0.0.1:40123');
        $server = app(SmppServer::class);
        $server->attach($connection);

        return [$server, $connection, $clientSide];
    };

    /** Write a PDU the way an ESME would. */
    $this->send = function ($clientSide, SmppPdu $pdu): void {
        fwrite($clientSide, $pdu->toBytes());
    };

    /** Read the first PDU the server answered with. */
    $this->receive = function ($clientSide): ?SmppPdu {
        $raw = fread($clientSide, 65535);

        if ($raw === false || $raw === '') {
            return null;
        }

        [$pdus] = SmppPdu::frames($raw);

        return $pdus[0] ?? null;
    };
});

it('accepts a bind for an issued account and records the session', function (): void {
    [$server, $connection, $client] = ($this->connect)();

    ($this->send)($client, SmppPdu::bind(SmppCommand::BindTransceiver, 1, 'ESMETEST', 'smpp-pass'));
    $server->tick();

    $response = ($this->receive)($client);

    expect($response)->not->toBeNull()
        ->and($response->command)->toBe(SmppCommand::BindTransceiverResp)
        ->and($response->status)->toBe(SmppStatus::Ok)
        ->and($response->sequence)->toBe(1)
        ->and($connection->isBound())->toBeTrue()
        ->and($connection->bindMode())->toBe(SmppBindMode::Transceiver);

    $session = SmppSession::query()->sole();

    expect($session->system_id)->toBe('ESMETEST')
        ->and($session->unbound_at)->toBeNull();

    expect($this->account->refresh()->last_bound_at)->not->toBeNull();
});

it('refuses a bind with the wrong password', function (): void {
    [$server, $connection, $client] = ($this->connect)();

    ($this->send)($client, SmppPdu::bind(SmppCommand::BindTransceiver, 4, 'ESMETEST', 'wrong'));
    $server->tick();

    $response = ($this->receive)($client);

    expect($response?->status)->toBe(SmppStatus::InvalidPassword)
        ->and($response?->sequence)->toBe(4)
        ->and($connection->isBound())->toBeFalse()
        ->and(SmppSession::query()->count())->toBe(0);
});

it('refuses a bind for a system id that was never issued', function (): void {
    [$server, , $client] = ($this->connect)();

    ($this->send)($client, SmppPdu::bind(SmppCommand::BindTransmitter, 2, 'UNKNOWN', 'smpp-pass'));
    $server->tick();

    expect(($this->receive)($client)?->status)->toBe(SmppStatus::InvalidSystemId);
});

it('refuses every bind while the server is switched off', function (): void {
    config()->set('smpp.enabled', false);

    [$server, , $client] = ($this->connect)();

    ($this->send)($client, SmppPdu::bind(SmppCommand::BindTransceiver, 3, 'ESMETEST', 'smpp-pass'));
    $server->tick();

    expect(($this->receive)($client)?->status)->toBe(SmppStatus::BindFailed);
});

it('refuses a bind the account was not provisioned for', function (): void {
    $this->account->forceFill(['bind_mode' => SmppBindMode::Transmitter])->save();

    [$server, , $client] = ($this->connect)();

    ($this->send)($client, SmppPdu::bind(SmppCommand::BindReceiver, 5, 'ESMETEST', 'smpp-pass'));
    $server->tick();

    expect(($this->receive)($client)?->status)->toBe(SmppStatus::BindFailed);
});

it('ingests a submit_sm, answers with the message id and queues the transport', function (): void {
    [$server, $connection, $client] = ($this->connect)();

    ($this->send)($client, SmppPdu::bind(SmppCommand::BindTransceiver, 1, 'ESMETEST', 'smpp-pass'));
    $server->tick();
    ($this->receive)($client);

    ($this->send)($client, new SmppPdu(SmppCommand::SubmitSm, 2, SmppStatus::Ok, [
        'service_type' => '',
        'source_addr_ton' => 5,
        'source_addr_npi' => 0,
        'source_addr' => 'OMNICAST',
        'dest_addr_ton' => 1,
        'dest_addr_npi' => 1,
        'destination_addr' => '0244000001',
        'esm_class' => 0,
        'protocol_id' => 0,
        'priority_flag' => 0,
        'schedule_delivery_time' => '',
        'validity_period' => '',
        'registered_delivery' => 1,
        'replace_if_present_flag' => 0,
        'data_coding' => 0,
        'sm_default_msg_id' => 0,
        'short_message' => 'Balance is ready',
    ]));

    $server->tick();

    $response = ($this->receive)($client);

    expect($response?->command)->toBe(SmppCommand::SubmitSmResp)
        ->and($response?->status)->toBe(SmppStatus::Ok);

    $message = SmppMessage::query()->sole();

    expect($message->client_id)->toBe($this->client->id)
        ->and($message->smpp_account_id)->toBe($this->account->id)
        ->and($message->destination_address)->toBe('+233244000001')
        ->and($message->source_address)->toBe('OMNICAST')
        ->and($message->body)->toBe('Balance is ready')
        ->and($message->segments)->toBe(1)
        ->and($message->status)->toBe(SmppMessageStatus::Accepted)
        ->and($response?->field('message_id'))->toBe($message->message_id);

    Queue::assertPushed(DeliverSmppMessage::class);

    // The accepted receipt is handed over in the same tick because the ESME is bound.
    expect(SmppDelivery::query()->count())->toBe(1)
        ->and(SmppDelivery::query()->sole()->stat)->toBe(SmppMessageStatus::Accepted->stat());
});

it('refuses a submit_sm on a receiver bind', function (): void {
    $this->account->forceFill(['bind_mode' => SmppBindMode::Receiver])->save();

    [$server, , $client] = ($this->connect)();

    ($this->send)($client, SmppPdu::bind(SmppCommand::BindReceiver, 1, 'ESMETEST', 'smpp-pass'));
    $server->tick();
    ($this->receive)($client);

    ($this->send)($client, new SmppPdu(SmppCommand::SubmitSm, 2, SmppStatus::Ok, [
        'service_type' => '',
        'source_addr_ton' => 1,
        'source_addr_npi' => 1,
        'source_addr' => 'OMNICAST',
        'dest_addr_ton' => 1,
        'dest_addr_npi' => 1,
        'destination_addr' => '+233244000001',
        'esm_class' => 0,
        'protocol_id' => 0,
        'priority_flag' => 0,
        'schedule_delivery_time' => '',
        'validity_period' => '',
        'registered_delivery' => 1,
        'replace_if_present_flag' => 0,
        'data_coding' => 0,
        'sm_default_msg_id' => 0,
        'short_message' => 'Hello',
    ]));

    $server->tick();

    expect(($this->receive)($client)?->status)->toBe(SmppStatus::InvalidBindStatus)
        ->and(SmppMessage::query()->count())->toBe(0);
});

it('refuses a submit_sm to an unusable destination', function (): void {
    [$server, , $client] = ($this->connect)();

    ($this->send)($client, SmppPdu::bind(SmppCommand::BindTransceiver, 1, 'ESMETEST', 'smpp-pass'));
    $server->tick();
    ($this->receive)($client);

    ($this->send)($client, new SmppPdu(SmppCommand::SubmitSm, 2, SmppStatus::Ok, [
        'service_type' => '',
        'source_addr_ton' => 1,
        'source_addr_npi' => 1,
        'source_addr' => 'OMNICAST',
        'dest_addr_ton' => 1,
        'dest_addr_npi' => 1,
        'destination_addr' => '12',
        'esm_class' => 0,
        'protocol_id' => 0,
        'priority_flag' => 0,
        'schedule_delivery_time' => '',
        'validity_period' => '',
        'registered_delivery' => 1,
        'replace_if_present_flag' => 0,
        'data_coding' => 0,
        'sm_default_msg_id' => 0,
        'short_message' => 'Hello',
    ]));

    $server->tick();

    expect(($this->receive)($client)?->status)->toBe(SmppStatus::InvalidDestinationAddress);
});

it('answers a keepalive and closes an unbind', function (): void {
    [$server, $connection, $client] = ($this->connect)();

    ($this->send)($client, SmppPdu::bind(SmppCommand::BindTransceiver, 1, 'ESMETEST', 'smpp-pass'));
    $server->tick();
    ($this->receive)($client);

    ($this->send)($client, SmppPdu::enquireLink(9));
    $server->tick();

    expect(($this->receive)($client)?->command)->toBe(SmppCommand::EnquireLinkResp);

    ($this->send)($client, new SmppPdu(SmppCommand::Unbind, 10));
    $server->tick();

    expect(($this->receive)($client)?->command)->toBe(SmppCommand::UnbindResp)
        ->and(SmppSession::query()->sole()->unbound_at)->not->toBeNull()
        ->and($connection->isBound())->toBeFalse();
});

it('hands the queued receipt to the bound session as a deliver_sm', function (): void {
    [$server, , $client] = ($this->connect)();

    ($this->send)($client, SmppPdu::bind(SmppCommand::BindTransceiver, 1, 'ESMETEST', 'smpp-pass'));
    $server->tick();
    ($this->receive)($client);

    $message = SmppMessage::factory()->for($this->client)->create([
        'smpp_account_id' => $this->account->id,
        'status' => SmppMessageStatus::Submitted,
        'submitted_at' => now(),
    ]);

    app(SmppReceiptRecorder::class)->queue($message, SmppMessageStatus::Delivered);

    expect($server->flushReceipts())->toBe(1);

    $receipt = ($this->receive)($client);

    expect($receipt?->command)->toBe(SmppCommand::DeliverSm)
        ->and($receipt?->field('esm_class'))->toBe(SmppPdu::ESM_CLASS_DELIVERY_RECEIPT)
        ->and($receipt?->field('destination_addr'))->toBe($message->source_address)
        ->and($receipt?->stringTlv(SmppPdu::RECEIPTED_MESSAGE_ID_TLV))->toBe($message->message_id)
        ->and($receipt?->integerTlv(SmppPdu::MESSAGE_STATE_TLV))->toBe(SmppMessageStatus::Delivered->messageState())
        ->and($receipt?->field('short_message'))->toContain('stat:DELIVRD');

    expect(SmppDelivery::query()->pending()->count())->toBe(0);
});

it('applies a network receipt to the SMPP message and reports it back to the ESME', function (): void {
    $message = SmppMessage::factory()->for($this->client)->create([
        'smpp_account_id' => $this->account->id,
        'status' => SmppMessageStatus::Submitted,
        'submitted_at' => now(),
    ]);

    $applied = app(SmppReceiptRecorder::class)->apply(new SmsDeliveryReport(
        reference: $message->reference,
        status: SmsMessageStatus::Delivered,
        providerStatus: 'DELIVRD',
    ));

    expect($applied?->id)->toBe($message->id);

    $message->refresh();

    expect($message->status)->toBe(SmppMessageStatus::Delivered)
        ->and($message->delivered_at)->not->toBeNull()
        ->and(SmppDelivery::query()->pending()->count())->toBe(1);
});

it('ignores a receipt for a reference that is not an SMPP submission', function (): void {
    expect(app(SmppReceiptRecorder::class)->apply(new SmsDeliveryReport(
        reference: 'not-an-smpp-reference',
        status: SmsMessageStatus::Delivered,
    )))->toBeNull();
});
