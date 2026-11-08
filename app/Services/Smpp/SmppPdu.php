<?php

namespace App\Services\Smpp;

use App\Enums\SmppCommand;
use App\Enums\SmppStatus;
use InvalidArgumentException;

/**
 * One SMPP 3.4 protocol data unit, in both directions.
 *
 * The codec is stateless: parse a frame, encode a frame. A socket is a byte
 * stream, so `frames()` splits a buffer into whole PDUs and hands the remainder
 * back, which is what lets a partial PDU wait in the connection until its last
 * byte arrives.
 */
final class SmppPdu
{
    /**
     * The TLV carrying the final state of a receipted message.
     */
    public const MESSAGE_STATE_TLV = 0x0427;

    /**
     * The TLV carrying the id of the message a receipt belongs to.
     */
    public const RECEIPTED_MESSAGE_ID_TLV = 0x001E;

    /**
     * The `esm_class` bit that marks a `deliver_sm` as a delivery receipt.
     */
    public const ESM_CLASS_DELIVERY_RECEIPT = 0x04;

    /**
     * The interface version OmniCast answers a bind with (SMPP 3.4).
     */
    public const INTERFACE_VERSION = 0x34;

    /**
     * The fixed size of an SMPP header.
     */
    public const HEADER_BYTES = 16;

    /**
     * @param  array<string, mixed>  $body
     * @param  array<int, string>  $tlvs  raw binary values keyed by tag
     */
    public function __construct(
        public readonly SmppCommand $command,
        public readonly int $sequence,
        public readonly SmppStatus $status = SmppStatus::Ok,
        public readonly array $body = [],
        public readonly array $tlvs = [],
    ) {}

    /**
     * Read a field out of the parsed body.
     */
    public function field(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    /**
     * The value of an optional parameter, decoded as an integer when present.
     */
    public function integerTlv(int $tag): ?int
    {
        $value = $this->tlvs[$tag] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        return strlen($value) === 1
            ? ord($value)
            : unpack('N', str_pad($value, 4, "\0", STR_PAD_LEFT))[1];
    }

    /**
     * The value of an optional parameter, decoded as a C-Octet string.
     */
    public function stringTlv(int $tag): ?string
    {
        $value = $this->tlvs[$tag] ?? null;

        return $value === null ? null : rtrim($value, "\0");
    }

    /**
     * Serialise the PDU, header included.
     */
    public function toBytes(): string
    {
        $body = $this->encodeBody();
        $tlvs = '';

        foreach ($this->tlvs as $tag => $value) {
            $tlvs .= pack('nn', $tag, strlen($value)).$value;
        }

        $length = self::HEADER_BYTES + strlen($body) + strlen($tlvs);

        return pack('NNNN', $length, $this->command->value, $this->status->value, $this->sequence)
            .$body
            .$tlvs;
    }

    /**
     * Split a socket buffer into whole PDUs and whatever is still incomplete.
     *
     * @return array{0: list<self>, 1: string}
     */
    public static function frames(string $buffer): array
    {
        $pdus = [];
        $offset = 0;
        $available = strlen($buffer);
        $max = max((int) config('smpp.limits.max_pdu_bytes', 65535), self::HEADER_BYTES);

        while ($available - $offset >= 4) {
            $length = unpack('N', substr($buffer, $offset, 4))[1];

            if ($length < self::HEADER_BYTES || $length > $max) {
                throw new InvalidArgumentException("An SMPP PDU announced an impossible length of {$length} bytes.");
            }

            if ($available - $offset < $length) {
                break;
            }

            $pdus[] = self::parse(substr($buffer, $offset, $length));
            $offset += $length;
        }

        return [$pdus, substr($buffer, $offset)];
    }

    /**
     * Parse one complete PDU.
     */
    public static function parse(string $bytes): self
    {
        if (strlen($bytes) < self::HEADER_BYTES) {
            throw new InvalidArgumentException('An SMPP PDU must be at least 16 bytes long.');
        }

        $header = unpack('Nlength/Ncommand/Nstatus/Nsequence', substr($bytes, 0, self::HEADER_BYTES));

        if ($header === false) {
            throw new InvalidArgumentException('The SMPP header could not be read.');
        }

        $command = SmppCommand::tryFrom($header['command']);

        if ($command === null) {
            throw new InvalidArgumentException(sprintf('SMPP command 0x%08X is not supported.', $header['command']));
        }

        [$body, $tlvs] = self::decodeBody($command, substr($bytes, self::HEADER_BYTES));

        return new self(
            $command,
            $header['sequence'],
            SmppStatus::tryFrom($header['status']) ?? SmppStatus::SystemError,
            $body,
            $tlvs,
        );
    }

    /**
     * A `bind_*` an ESME would send; used by the server's own tests.
     */
    public static function bind(
        SmppCommand $command,
        int $sequence,
        string $systemId,
        string $password,
        string $systemType = '',
        int $interfaceVersion = self::INTERFACE_VERSION,
    ): self {
        return new self($command, $sequence, SmppStatus::Ok, [
            'system_id' => $systemId,
            'password' => $password,
            'system_type' => $systemType,
            'interface_version' => $interfaceVersion,
            'addr_ton' => 0,
            'addr_npi' => 0,
            'address_range' => '',
        ]);
    }

    /**
     * The answer to a bind, carrying the system id the server accepted.
     */
    public static function bindResponse(
        SmppCommand $command,
        int $sequence,
        string $systemId,
        SmppStatus $status = SmppStatus::Ok,
    ): self {
        $response = $command->response() ?? $command;

        return new self($response, $sequence, $status, $status->isOk() ? ['system_id' => $systemId] : []);
    }

    /**
     * The answer to a `submit_sm`, carrying the id the ESME must quote later.
     */
    public static function submitSmResponse(int $sequence, string $messageId = '', SmppStatus $status = SmppStatus::Ok): self
    {
        return new self(SmppCommand::SubmitSmResp, $sequence, $status, $status->isOk() ? ['message_id' => $messageId] : []);
    }

    /**
     * A delivery receipt for a message an ESME submitted.
     *
     * The receipt is addressed the way the original message came in: from the
     * handset back to the ESME, with the outcome in `stat` and the original id
     * in the `receipted_message_id` optional parameter.
     */
    public static function deliverSm(
        int $sequence,
        string $from,
        string $to,
        string $receipt,
        string $receiptedMessageId,
        int $messageState,
    ): self {
        return new self(
            SmppCommand::DeliverSm,
            $sequence,
            SmppStatus::Ok,
            [
                'service_type' => '',
                'source_addr_ton' => 1,
                'source_addr_npi' => 1,
                'source_addr' => $from,
                'dest_addr_ton' => 1,
                'dest_addr_npi' => 1,
                'destination_addr' => $to,
                'esm_class' => self::ESM_CLASS_DELIVERY_RECEIPT,
                'protocol_id' => 0,
                'priority_flag' => 0,
                'schedule_delivery_time' => '',
                'validity_period' => '',
                'registered_delivery' => 0,
                'replace_if_present_flag' => 0,
                'data_coding' => 0,
                'sm_default_msg_id' => 0,
                'short_message' => $receipt,
            ],
            [
                self::MESSAGE_STATE_TLV => chr($messageState),
                self::RECEIPTED_MESSAGE_ID_TLV => $receiptedMessageId."\0",
            ],
        );
    }

    /**
     * The ESME's answer to a delivery receipt.
     */
    public static function deliverSmResponse(int $sequence, string $messageId = '', SmppStatus $status = SmppStatus::Ok): self
    {
        return new self(SmppCommand::DeliverSmResp, $sequence, $status, $status->isOk() ? ['message_id' => $messageId] : []);
    }

    /**
     * The keepalive the server sends a quiet session.
     */
    public static function enquireLink(int $sequence): self
    {
        return new self(SmppCommand::EnquireLink, $sequence);
    }

    /**
     * The answer to a keepalive.
     */
    public static function enquireLinkResponse(int $sequence): self
    {
        return new self(SmppCommand::EnquireLinkResp, $sequence);
    }

    /**
     * The answer to a teardown request.
     */
    public static function unbindResponse(int $sequence, SmppStatus $status = SmppStatus::Ok): self
    {
        return new self(SmppCommand::UnbindResp, $sequence, $status);
    }

    /**
     * A refusal for a command that cannot be answered in its own shape.
     */
    public static function genericNack(int $sequence, SmppStatus $status = SmppStatus::SystemError): self
    {
        return new self(SmppCommand::GenericNack, $sequence, $status);
    }

    /**
     * Write the body fields the command defines.
     */
    protected function encodeBody(): string
    {
        return match ($this->command) {
            SmppCommand::BindReceiver,
            SmppCommand::BindTransmitter,
            SmppCommand::BindTransceiver => self::cOctetString((string) $this->field('system_id', ''), 16)
                .self::cOctetString((string) $this->field('password', ''), 9)
                .self::cOctetString((string) $this->field('system_type', ''), 13)
                .chr((int) $this->field('interface_version', self::INTERFACE_VERSION))
                .chr((int) $this->field('addr_ton', 0))
                .chr((int) $this->field('addr_npi', 0))
                .self::cOctetString((string) $this->field('address_range', ''), 41),

            SmppCommand::BindReceiverResp,
            SmppCommand::BindTransmitterResp,
            SmppCommand::BindTransceiverResp => self::cOctetString((string) $this->field('system_id', ''), 16),

            SmppCommand::SubmitSmResp,
            SmppCommand::DeliverSmResp => self::cOctetString((string) $this->field('message_id', ''), 65),

            SmppCommand::SubmitSm,
            SmppCommand::DeliverSm => $this->encodeMessageBody(),

            default => '',
        };
    }

    /**
     * Write the shared `submit_sm` / `deliver_sm` body.
     */
    protected function encodeMessageBody(): string
    {
        $shortMessage = (string) $this->field('short_message', '');

        return self::cOctetString((string) $this->field('service_type', ''), 6)
            .chr((int) $this->field('source_addr_ton', 1))
            .chr((int) $this->field('source_addr_npi', 1))
            .self::cOctetString((string) $this->field('source_addr', ''), 21)
            .chr((int) $this->field('dest_addr_ton', 1))
            .chr((int) $this->field('dest_addr_npi', 1))
            .self::cOctetString((string) $this->field('destination_addr', ''), 21)
            .chr((int) $this->field('esm_class', 0))
            .chr((int) $this->field('protocol_id', 0))
            .chr((int) $this->field('priority_flag', 0))
            .self::cOctetString((string) $this->field('schedule_delivery_time', ''), 17)
            .self::cOctetString((string) $this->field('validity_period', ''), 17)
            .chr((int) $this->field('registered_delivery', 1))
            .chr((int) $this->field('replace_if_present_flag', 0))
            .chr((int) $this->field('data_coding', 0))
            .chr((int) $this->field('sm_default_msg_id', 0))
            .chr(min(strlen($shortMessage), 255))
            .substr($shortMessage, 0, 255);
    }

    /**
     * Read the body fields and optional parameters of a PDU.
     *
     * @return array{0: array<string, mixed>, 1: array<int, string>}
     */
    protected static function decodeBody(SmppCommand $command, string $payload): array
    {
        return match ($command) {
            SmppCommand::BindReceiver,
            SmppCommand::BindTransmitter,
            SmppCommand::BindTransceiver => self::decodeBind($payload),

            SmppCommand::BindReceiverResp,
            SmppCommand::BindTransmitterResp,
            SmppCommand::BindTransceiverResp => [['system_id' => rtrim(substr($payload, 0, 16), "\0")], []],

            SmppCommand::SubmitSmResp,
            SmppCommand::DeliverSmResp => [['message_id' => rtrim(substr($payload, 0, 65), "\0")], []],

            SmppCommand::SubmitSm,
            SmppCommand::DeliverSm => self::decodeMessageBody($payload),

            default => [[], []],
        };
    }

    /**
     * Read a bind body.
     *
     * @return array{0: array<string, mixed>, 1: array<int, string>}
     */
    protected static function decodeBind(string $payload): array
    {
        if ($payload === '') {
            return [[], []];
        }

        $offset = 0;

        $systemId = self::readCOctet($payload, $offset);
        $password = self::readCOctet($payload, $offset);
        $systemType = self::readCOctet($payload, $offset);
        $interfaceVersion = self::readInt1($payload, $offset);
        $addressTon = self::readInt1($payload, $offset);
        $addressNpi = self::readInt1($payload, $offset);
        $addressRange = self::readCOctet($payload, $offset);

        return [[
            'system_id' => $systemId,
            'password' => $password,
            'system_type' => $systemType,
            'interface_version' => $interfaceVersion,
            'addr_ton' => $addressTon,
            'addr_npi' => $addressNpi,
            'address_range' => $addressRange,
        ], []];
    }

    /**
     * Read a `submit_sm` / `deliver_sm` body, including its optional parameters.
     *
     * @return array{0: array<string, mixed>, 1: array<int, string>}
     */
    protected static function decodeMessageBody(string $payload): array
    {
        if ($payload === '') {
            return [[], []];
        }

        $offset = 0;
        $body = [
            'service_type' => self::readCOctet($payload, $offset),
            'source_addr_ton' => self::readInt1($payload, $offset),
            'source_addr_npi' => self::readInt1($payload, $offset),
            'source_addr' => self::readCOctet($payload, $offset),
            'dest_addr_ton' => self::readInt1($payload, $offset),
            'dest_addr_npi' => self::readInt1($payload, $offset),
            'destination_addr' => self::readCOctet($payload, $offset),
            'esm_class' => self::readInt1($payload, $offset),
            'protocol_id' => self::readInt1($payload, $offset),
            'priority_flag' => self::readInt1($payload, $offset),
            'schedule_delivery_time' => self::readCOctet($payload, $offset),
            'validity_period' => self::readCOctet($payload, $offset),
            'registered_delivery' => self::readInt1($payload, $offset),
            'replace_if_present_flag' => self::readInt1($payload, $offset),
            'data_coding' => self::readInt1($payload, $offset),
            'sm_default_msg_id' => self::readInt1($payload, $offset),
        ];

        $declared = self::readInt1($payload, $offset);
        $body['short_message'] = substr($payload, $offset, $declared);
        $offset += $declared;

        return [$body, self::decodeTlvs($payload, $offset)];
    }

    /**
     * Read the optional parameters that follow a short message.
     *
     * @return array<int, string>
     */
    protected static function decodeTlvs(string $payload, int $offset): array
    {
        $tlvs = [];
        $available = strlen($payload);

        while ($offset + 4 <= $available) {
            $header = unpack('ntag/nlength', substr($payload, $offset, 4));

            if ($header === false) {
                break;
            }

            $offset += 4;
            $tlvs[$header['tag']] = substr($payload, $offset, $header['length']);
            $offset += $header['length'];
        }

        return $tlvs;
    }

    /**
     * Read a C-Octet string and step past its terminator.
     */
    protected static function readCOctet(string $payload, int &$offset): string
    {
        $length = strlen($payload);
        $terminator = strpos($payload, "\0", $offset);
        $end = $terminator === false ? $length : $terminator;
        $value = substr($payload, $offset, $end - $offset);
        $offset = min($end + 1, $length);

        return $value;
    }

    /**
     * Read a one-byte integer and step past it.
     */
    protected static function readInt1(string $payload, int &$offset): int
    {
        if ($offset >= strlen($payload)) {
            return 0;
        }

        return ord($payload[$offset++]);
    }

    /**
     * Encode a C-Octet string, truncated to the field's maximum width.
     */
    protected static function cOctetString(string $value, int $maxBytes): string
    {
        return substr($value, 0, $maxBytes)."\0";
    }
}
