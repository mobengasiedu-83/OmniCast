<?php

namespace App\Enums;

/**
 * The event that enrols a contact (or the tenant itself) into a workflow.
 *
 * A trigger is fired from the place the app already changes state, so a workflow
 * never polls: the contact book, the campaign pipeline and the scheduler all
 * hand the event to the automation engine after their own work is committed.
 */
enum AutomationTrigger: string
{
    case ContactCreated = 'contact.created';
    case ContactTagged = 'contact.tagged';
    case CampaignLaunched = 'campaign.launched';
    case CampaignCompleted = 'campaign.completed';
    case Schedule = 'schedule';

    public function label(): string
    {
        return match ($this) {
            self::ContactCreated => 'Contact created',
            self::ContactTagged => 'Contact tagged',
            self::CampaignLaunched => 'Campaign launched',
            self::CampaignCompleted => 'Campaign completed',
            self::Schedule => 'On a schedule',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ContactCreated => 'When a contact is added to the book, by hand or from a form.',
            self::ContactTagged => 'When a contact carries the chosen tag, on creation or on edit.',
            self::CampaignLaunched => 'When a campaign is launched for the tenant.',
            self::CampaignCompleted => 'When a campaign finishes delivering.',
            self::Schedule => 'Once a day, at the hour the workflow chooses.',
        };
    }

    /**
     * Whether the trigger needs the contact book to fire.
     */
    public function needsContact(): bool
    {
        return in_array($this, [self::ContactCreated, self::ContactTagged], true);
    }

    /**
     * Whether the trigger is fired by the scheduler rather than by an action.
     */
    public function isScheduled(): bool
    {
        return $this === self::Schedule;
    }

    /**
     * The trigger values, as stored on a workflow.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $trigger): string => $trigger->value, self::cases());
    }
}
