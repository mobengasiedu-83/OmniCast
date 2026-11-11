<?php

namespace App\Enums;

enum PrivacyRetentionCategory: string
{
    case ReadNotifications = 'read_notifications';
    case SentAnnouncements = 'sent_announcements';
    case ReportExports = 'report_exports';
    case WebhookDeliveries = 'webhook_deliveries';
    case AutomationRuns = 'automation_runs';
    case FailedJobs = 'failed_jobs';
    case JobBatches = 'job_batches';

    public function label(): string
    {
        return match ($this) {
            self::ReadNotifications => 'Read inbox notifications',
            self::SentAnnouncements => 'Sent announcement records',
            self::ReportExports => 'Generated report files',
            self::WebhookDeliveries => 'Completed webhook delivery records',
            self::AutomationRuns => 'Finished automation runs',
            self::FailedJobs => 'Failed queue jobs',
            self::JobBatches => 'Completed queue batches',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ReadNotifications => 'Read notifications are eligible for removal after the approved period; unread notices are never pruned.',
            self::SentAnnouncements => 'Sent announcements and their delivery receipts are eligible after the approved period.',
            self::ReportExports => 'Generated CSV files are deleted after the approved period; export history remains.',
            self::WebhookDeliveries => 'Final webhook delivery rows are eligible after the approved period; open retries remain.',
            self::AutomationRuns => 'Completed, failed and cancelled runs are eligible after the approved period; active runs remain.',
            self::FailedJobs => 'Failed queue jobs are eligible after the approved period.',
            self::JobBatches => 'Completed queue batches are eligible after the approved period.',
        };
    }
}
