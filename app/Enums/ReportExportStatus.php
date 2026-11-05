<?php

namespace App\Enums;

/**
 * The lifecycle of a report file the platform wrote to storage.
 *
 * Exports are generated on a queue, so a row is created as `pending` and only
 * offers a download once the job has written the file.
 */
enum ReportExportStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Queued',
            self::Processing => 'Generating',
            self::Completed => 'Ready',
            self::Failed => 'Failed',
        };
    }

    /**
     * Whether the export reached a terminal state.
     */
    public function isFinished(): bool
    {
        return in_array($this, [self::Completed, self::Failed], true);
    }

    /**
     * Whether the export can be downloaded.
     */
    public function isDownloadable(): bool
    {
        return $this === self::Completed;
    }
}
