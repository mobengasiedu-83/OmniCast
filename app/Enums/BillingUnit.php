<?php

namespace App\Enums;

/**
 * How a rate is converted into a billable amount.
 */
enum BillingUnit: string
{
    case PerSecond = 'per_second';
    case PerThirtySeconds = 'per_30_seconds';
    case PerMinute = 'per_minute';
    case RoundedMinute = 'rounded_minute';
    case PerSuccessfulCall = 'per_successful_call';
    case PerSmsPart = 'per_sms_part';
    case PerEmail = 'per_email';
    case FixedCampaign = 'fixed_campaign';

    public function label(): string
    {
        return match ($this) {
            self::PerSecond => 'Per Second',
            self::PerThirtySeconds => 'Per 30 Seconds',
            self::PerMinute => 'Per Minute',
            self::RoundedMinute => 'Rounded Minute',
            self::PerSuccessfulCall => 'Per Successful Call',
            self::PerSmsPart => 'Per SMS Part',
            self::PerEmail => 'Per Email',
            self::FixedCampaign => 'Fixed Campaign',
        };
    }

    public function isVoiceUnit(): bool
    {
        return in_array($this, [
            self::PerSecond,
            self::PerThirtySeconds,
            self::PerMinute,
            self::RoundedMinute,
            self::PerSuccessfulCall,
            self::FixedCampaign,
        ], true);
    }
}
