<?php

namespace App\Enums;

/**
 * The SMPP 3.4 command ids the server speaks.
 *
 * Only the subset OmniCast needs is listed: the three binds, the two message
 * operations, the keepalive and the two teardown commands. Anything else is
 * answered with `generic_nack`, which is what the specification asks for.
 */
enum SmppCommand: int
{
    case GenericNack = 0x80000000;
    case BindReceiver = 0x00000001;
    case BindReceiverResp = 0x80000001;
    case BindTransmitter = 0x00000002;
    case BindTransmitterResp = 0x80000002;
    case SubmitSm = 0x00000004;
    case SubmitSmResp = 0x80000004;
    case DeliverSm = 0x00000005;
    case DeliverSmResp = 0x80000005;
    case Unbind = 0x00000006;
    case UnbindResp = 0x80000006;
    case BindTransceiver = 0x00000009;
    case BindTransceiverResp = 0x80000009;
    case EnquireLink = 0x00000015;
    case EnquireLinkResp = 0x80000015;

    public function label(): string
    {
        return match ($this) {
            self::GenericNack => 'generic_nack',
            self::BindReceiver => 'bind_receiver',
            self::BindReceiverResp => 'bind_receiver_resp',
            self::BindTransmitter => 'bind_transmitter',
            self::BindTransmitterResp => 'bind_transmitter_resp',
            self::SubmitSm => 'submit_sm',
            self::SubmitSmResp => 'submit_sm_resp',
            self::DeliverSm => 'deliver_sm',
            self::DeliverSmResp => 'deliver_sm_resp',
            self::Unbind => 'unbind',
            self::UnbindResp => 'unbind_resp',
            self::BindTransceiver => 'bind_transceiver',
            self::BindTransceiverResp => 'bind_transceiver_resp',
            self::EnquireLink => 'enquire_link',
            self::EnquireLinkResp => 'enquire_link_resp',
        };
    }

    /**
     * The successful answer to this request, or null when it is already one.
     */
    public function response(): ?self
    {
        return match ($this) {
            self::BindReceiver => self::BindReceiverResp,
            self::BindTransmitter => self::BindTransmitterResp,
            self::BindTransceiver => self::BindTransceiverResp,
            self::SubmitSm => self::SubmitSmResp,
            self::DeliverSm => self::DeliverSmResp,
            self::Unbind => self::UnbindResp,
            self::EnquireLink => self::EnquireLinkResp,
            default => null,
        };
    }

    /**
     * The bind mode this command opens, when it opens one.
     */
    public function bindMode(): ?SmppBindMode
    {
        return match ($this) {
            self::BindReceiver => SmppBindMode::Receiver,
            self::BindTransmitter => SmppBindMode::Transmitter,
            self::BindTransceiver => SmppBindMode::Transceiver,
            default => null,
        };
    }
}
