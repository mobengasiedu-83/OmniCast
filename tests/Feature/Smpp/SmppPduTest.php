<?php

use App\Enums\SmppCommand;
use App\Enums\SmppMessageStatus;
use App\Enums\SmppStatus;
use App\Services\Smpp\SmppPdu;

it('round-trips a bind with every field intact', function (): void {
    $pdu = SmppPdu::bind(SmppCommand::BindTransceiver, 7, 'ESMETEST', 'smpp-pass', 'TELCO');
    $parsed = SmppPdu::parse($pdu->toBytes());

    expect($parsed->command)->toBe(SmppCommand::BindTransceiver)
        ->and($parsed->sequence)->toBe(7)
        ->and($parsed->status)->toBe(SmppStatus::Ok)
        ->and($parsed->field('system_id'))->toBe('ESMETEST')
        ->and($parsed->field('password'))->toBe('smpp-pass')
        ->and($parsed->field('system_type'))->toBe('TELCO')
        ->and($parsed->field('interface_version'))->toBe(SmppPdu::INTERFACE_VERSION);
});

it('round-trips a submit_sm body and its optional parameters', function (): void {
    $pdu = new SmppPdu(SmppCommand::SubmitSm, 12, SmppStatus::Ok, [
        'service_type' => '',
        'source_addr_ton' => 5,
        'source_addr_npi' => 0,
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
        'short_message' => 'Hello world',
    ], [
        SmppPdu::RECEIPTED_MESSAGE_ID_TLV => "abc-123\0",
        SmppPdu::MESSAGE_STATE_TLV => chr(SmppMessageStatus::Delivered->messageState()),
    ]);

    $parsed = SmppPdu::parse($pdu->toBytes());

    expect($parsed->field('source_addr'))->toBe('OMNICAST')
        ->and($parsed->field('destination_addr'))->toBe('+233244000001')
        ->and($parsed->field('short_message'))->toBe('Hello world')
        ->and($parsed->field('registered_delivery'))->toBe(1)
        ->and($parsed->stringTlv(SmppPdu::RECEIPTED_MESSAGE_ID_TLV))->toBe('abc-123')
        ->and($parsed->integerTlv(SmppPdu::MESSAGE_STATE_TLV))->toBe(2);
});

it('splits a stream into whole PDUs and keeps the partial tail', function (): void {
    $first = SmppPdu::enquireLink(1)->toBytes();
    $second = SmppPdu::unbindResponse(2)->toBytes();

    [$pdus, $remainder] = SmppPdu::frames($first.$second.substr($second, 0, 6));

    expect($pdus)->toHaveCount(2)
        ->and($pdus[0]->command)->toBe(SmppCommand::EnquireLink)
        ->and($pdus[1]->command)->toBe(SmppCommand::UnbindResp)
        ->and($remainder)->toHaveLength(6);

    [$none, $partial] = SmppPdu::frames(substr($first, 0, 8));

    expect($none)->toBeEmpty()
        ->and($partial)->toHaveLength(8);
});

it('refuses a frame that announces an impossible length', function (): void {
    SmppPdu::frames(pack('NNNN', 3, SmppCommand::EnquireLink->value, 0, 1).'abc');
})->throws(InvalidArgumentException::class);

it('refuses a command the server does not speak', function (): void {
    SmppPdu::parse(pack('NNNN', 16, 0x00000055, 0, 1));
})->throws(InvalidArgumentException::class);

it('builds a delivery receipt the ESME can parse', function (): void {
    $receipt = SmppPdu::deliverSm(
        sequence: 4,
        from: '+233244000001',
        to: 'OMNICAST',
        receipt: 'id:abc sub:001 dlvrd:001 stat:DELIVRD err:000',
        receiptedMessageId: 'abc',
        messageState: SmppMessageStatus::Delivered->messageState(),
    );

    $parsed = SmppPdu::parse($receipt->toBytes());

    expect($parsed->command)->toBe(SmppCommand::DeliverSm)
        ->and($parsed->field('esm_class'))->toBe(SmppPdu::ESM_CLASS_DELIVERY_RECEIPT)
        ->and($parsed->field('source_addr'))->toBe('+233244000001')
        ->and($parsed->field('destination_addr'))->toBe('OMNICAST')
        ->and($parsed->field('short_message'))->toContain('stat:DELIVRD')
        ->and($parsed->stringTlv(SmppPdu::RECEIPTED_MESSAGE_ID_TLV))->toBe('abc');
});
