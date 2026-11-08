<?php

namespace App\Enums;

/**
 * The SMPP command status codes the server answers with.
 *
 * A bind that fails must say why: an ESME operator can see the difference
 * between a wrong password and a system id that was never issued.
 */
enum SmppStatus: int
{
    case Ok = 0x00000000;
    case InvalidMessageLength = 0x00000001;
    case InvalidCommandLength = 0x00000002;
    case InvalidCommandId = 0x00000003;
    case InvalidSourceAddress = 0x0000000A;
    case InvalidDestinationAddress = 0x0000000B;
    case InvalidBindStatus = 0x00000004;
    case BindFailed = 0x0000000D;
    case InvalidPassword = 0x0000000E;
    case InvalidSystemId = 0x0000000F;
    case Throttled = 0x00000058;
    case SystemError = 0x00000064;

    public function label(): string
    {
        return match ($this) {
            self::Ok => 'No error',
            self::InvalidMessageLength => 'Invalid message length',
            self::InvalidCommandLength => 'Invalid command length',
            self::InvalidCommandId => 'Invalid command id',
            self::InvalidSourceAddress => 'Invalid source address',
            self::InvalidDestinationAddress => 'Invalid destination address',
            self::InvalidBindStatus => 'Invalid bind status',
            self::BindFailed => 'Bind failed',
            self::InvalidPassword => 'Invalid password',
            self::InvalidSystemId => 'Invalid system id',
            self::Throttled => 'Throttled',
            self::SystemError => 'System error',
        };
    }

    public function isOk(): bool
    {
        return $this === self::Ok;
    }
}
