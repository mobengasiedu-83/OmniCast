<?php

namespace App\Enums;

/**
 * The events a webhook subscription can receive.
 *
 * The value is the name written onto the delivery and sent in
 * `X-Omnicast-Event`, so a subscriber switches on a stable string rather than a
 * class name. The group is what the subscription screen lists the event under.
 */
enum WebhookEvent: string
{
    case CampaignCreated = 'campaign.created';
    case CampaignSubmitted = 'campaign.submitted';
    case CampaignApproved = 'campaign.approved';
    case CampaignRejected = 'campaign.rejected';
    case CampaignLaunched = 'campaign.launched';
    case CampaignCompleted = 'campaign.completed';
    case CampaignCancelled = 'campaign.cancelled';
    case SmsDelivered = 'sms.delivered';
    case SmsFailed = 'sms.failed';
    case EmailDelivered = 'email.delivered';
    case EmailBounced = 'email.bounced';

    public function label(): string
    {
        return match ($this) {
            self::CampaignCreated => 'Campaign created',
            self::CampaignSubmitted => 'Campaign submitted for approval',
            self::CampaignApproved => 'Campaign approved',
            self::CampaignRejected => 'Campaign rejected',
            self::CampaignLaunched => 'Campaign launched',
            self::CampaignCompleted => 'Campaign completed',
            self::CampaignCancelled => 'Campaign cancelled',
            self::SmsDelivered => 'SMS delivered',
            self::SmsFailed => 'SMS failed',
            self::EmailDelivered => 'Email delivered',
            self::EmailBounced => 'Email bounced',
        };
    }

    /**
     * The group the subscription screen lists the event under.
     */
    public function group(): string
    {
        return match ($this) {
            self::SmsDelivered, self::SmsFailed => 'SMS',
            self::EmailDelivered, self::EmailBounced => 'Email',
            default => 'Campaigns',
        };
    }

    /**
     * The payload version this event is documented under.
     */
    public function version(): string
    {
        return 'v1';
    }

    /**
     * The event values, as stored in `webhook_endpoints.events`.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $event): string => $event->value, self::cases());
    }

    /**
     * Every event grouped for the subscription screens.
     *
     * @return list<array{group: string, events: list<array{value: string, label: string}>}>
     */
    public static function catalogue(): array
    {
        $groups = [];

        foreach (self::cases() as $event) {
            $groups[$event->group()][] = [
                'value' => $event->value,
                'label' => $event->label(),
            ];
        }

        return array_map(
            fn (string $group, array $events): array => ['group' => $group, 'events' => $events],
            array_keys($groups),
            array_values($groups),
        );
    }
}
