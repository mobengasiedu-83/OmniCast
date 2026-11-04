<?php

namespace App\Enums;

/**
 * The feedback events the mail server posts to the email webhook.
 */
enum EmailMessageEventType: string
{
    case Submitted = 'submitted';
    case Delivered = 'delivered';
    case Bounced = 'bounced';
    case Complained = 'complained';
    case Rejected = 'rejected';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted',
            self::Delivered => 'Delivered',
            self::Bounced => 'Bounced',
            self::Complained => 'Complained',
            self::Rejected => 'Rejected',
            self::Failed => 'Failed',
        };
    }

    /**
     * The message state this receipt moves the message into.
     */
    public function status(): EmailMessageStatus
    {
        return match ($this) {
            self::Submitted => EmailMessageStatus::Submitted,
            self::Delivered => EmailMessageStatus::Delivered,
            self::Bounced => EmailMessageStatus::Bounced,
            self::Complained => EmailMessageStatus::Complained,
            self::Rejected => EmailMessageStatus::Rejected,
            self::Failed => EmailMessageStatus::Failed,
        };
    }
}
