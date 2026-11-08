<?php

namespace App\Enums;

/**
 * What happened when the engine reached one step of a workflow.
 *
 * A skipped step is a success: the workflow decided the contact did not need it
 * (no email address, a condition that failed), which is different from a step
 * the provider rejected.
 */
enum AutomationStepStatus: string
{
    case Completed = 'completed';
    case Skipped = 'skipped';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Completed => 'Completed',
            self::Skipped => 'Skipped',
            self::Failed => 'Failed',
        };
    }

    /**
     * Whether the run may move on to the next step.
     */
    public function allowsProgression(): bool
    {
        return $this !== self::Failed;
    }
}
