<?php

namespace App\Enums;

/**
 * The work a single step in a workflow does.
 *
 * The audience steps keep the contact book tidy across channels, the message
 * steps talk to the contact on one channel, and the flow steps move the run
 * through the workflow without touching either.
 */
enum AutomationAction: string
{
    case SendSms = 'send_sms';
    case SendEmail = 'send_email';
    case AddTag = 'add_tag';
    case RemoveTag = 'remove_tag';
    case AddToList = 'add_to_list';
    case RemoveFromList = 'remove_from_list';
    case SetDnd = 'set_dnd';
    case LiftDnd = 'lift_dnd';
    case Condition = 'condition';
    case Wait = 'wait';
    case Webhook = 'webhook';
    case Stop = 'stop';

    public function label(): string
    {
        return match ($this) {
            self::SendSms => 'Send an SMS',
            self::SendEmail => 'Send an email',
            self::AddTag => 'Add a tag',
            self::RemoveTag => 'Remove a tag',
            self::AddToList => 'Add to a list',
            self::RemoveFromList => 'Remove from a list',
            self::SetDnd => 'Put the contact on DND',
            self::LiftDnd => 'Take the contact off DND',
            self::Condition => 'Only continue when…',
            self::Wait => 'Wait',
            self::Webhook => 'Call a webhook',
            self::Stop => 'End the workflow',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::SendSms => 'Texts the contact from the sender ID chosen for the workflow.',
            self::SendEmail => 'Emails the contact; skipped when the contact has no address.',
            self::AddTag => 'Attaches a tag so the contact joins the matching segment.',
            self::RemoveTag => 'Detaches a tag from the contact.',
            self::AddToList => 'Adds the contact to a static list.',
            self::RemoveFromList => 'Takes the contact out of a static list.',
            self::SetDnd => 'Records a do-not-disturb decision for the contact number.',
            self::LiftDnd => 'Lifts the do-not-disturb decision this tenant recorded.',
            self::Condition => 'Checks the contact and stops, or jumps ahead, when the check fails.',
            self::Wait => 'Pauses the run and resumes it after the delay.',
            self::Webhook => 'Posts a signed payload to a URL the workflow owns.',
            self::Stop => 'Ends the run early.',
        };
    }

    /**
     * The channel the step delivers on, when it delivers anything.
     */
    public function channel(): ?Channel
    {
        return match ($this) {
            self::SendSms => Channel::Sms,
            self::SendEmail => Channel::Email,
            default => null,
        };
    }

    /**
     * Whether the step needs a contact to act on.
     */
    public function needsContact(): bool
    {
        return in_array($this, [
            self::SendSms,
            self::SendEmail,
            self::AddTag,
            self::RemoveTag,
            self::AddToList,
            self::RemoveFromList,
            self::SetDnd,
            self::LiftDnd,
            self::Condition,
        ], true);
    }

    /**
     * Whether the step pauses the run instead of finishing it.
     */
    public function pausesRun(): bool
    {
        return $this === self::Wait;
    }

    /**
     * The action values, as stored on a step.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $action): string => $action->value, self::cases());
    }
}
