<?php

namespace App\Enums;

/**
 * The lifecycle of one worked-through workflow enrolment.
 */
enum AutomationRunStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Waiting = 'waiting';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Queued',
            self::Running => 'Running',
            self::Waiting => 'Waiting',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Whether the run has stopped moving for good.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Failed, self::Cancelled], true);
    }

    /**
     * Whether a worker still has something to do for this run.
     */
    public function isOpen(): bool
    {
        return ! $this->isTerminal();
    }
}
