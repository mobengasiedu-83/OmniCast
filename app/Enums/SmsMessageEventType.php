<?php

namespace App\Enums;

/**
 * The delivery receipt events the network posts to the SMS webhook.
 */
enum SmsMessageEventType: string
{
    case Submitted = 'submitted';
    case Delivered = 'delivered';
    case Undelivered = 'undelivered';
    case Expired = 'expired';
    case Rejected = 'rejected';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted',
            self::Delivered => 'Delivered',
            self::Undelivered => 'Undelivered',
            self::Expired => 'Expired',
            self::Rejected => 'Rejected',
            self::Failed => 'Failed',
        };
    }

    /**
     * The message state this receipt moves the message into.
     */
    public function status(): SmsMessageStatus
    {
        return match ($this) {
            self::Submitted => SmsMessageStatus::Submitted,
            self::Delivered => SmsMessageStatus::Delivered,
            self::Undelivered => SmsMessageStatus::Undelivered,
            self::Expired => SmsMessageStatus::Expired,
            self::Rejected => SmsMessageStatus::Rejected,
            self::Failed => SmsMessageStatus::Failed,
        };
    }
}
