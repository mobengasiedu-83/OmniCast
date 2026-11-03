<?php

namespace App\Enums;

/**
 * The lifecycle of a campaign, from draft through approval and delivery.
 */
enum CampaignStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Scheduled = 'scheduled';
    case Running = 'running';
    case Paused = 'paused';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingApproval => 'Pending Approval',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Scheduled => 'Scheduled',
            self::Running => 'Running',
            self::Paused => 'Paused',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Rejected], true);
    }

    public function canBeSubmitted(): bool
    {
        return in_array($this, [self::Draft, self::Rejected], true);
    }

    public function canBeApproved(): bool
    {
        return $this === self::PendingApproval;
    }

    public function canBeScheduled(): bool
    {
        return $this === self::Approved;
    }

    public function holdsWalletFunds(): bool
    {
        return in_array($this, [
            self::PendingApproval,
            self::Approved,
            self::Scheduled,
            self::Running,
            self::Paused,
        ], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Failed, self::Cancelled], true);
    }
}
