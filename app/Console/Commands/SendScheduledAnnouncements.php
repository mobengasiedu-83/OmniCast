<?php

namespace App\Console\Commands;

use App\Models\Announcement;
use App\Services\Notifications\AnnouncementDispatcher;
use Illuminate\Console\Command;

/**
 * Sends the announcements whose composer-chosen time has arrived.
 *
 * A scheduled announcement is not sitting on the queue as a delayed job: holding
 * one until next Monday would keep a serialized model in Redis for a week and a
 * deployment would strand it. The row is the schedule instead, and this scan is
 * what releases it, so a restart costs at most the minute the scan runs in.
 */
class SendScheduledAnnouncements extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'announcements:send-scheduled
        {--limit=50 : The maximum number of announcements to release in one run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Queue every announcement whose scheduled send time has arrived';

    /**
     * Execute the console command.
     */
    public function handle(AnnouncementDispatcher $dispatcher): int
    {
        $limit = max((int) $this->option('limit'), 1);
        $released = 0;

        Announcement::query()
            ->due()
            ->orderBy('scheduled_at')
            ->limit($limit)
            ->get()
            ->each(function (Announcement $announcement) use ($dispatcher, &$released): void {
                $dispatcher->queue($announcement);

                $released++;
            });

        $this->info($released === 0
            ? 'No scheduled announcements were due.'
            : "Queued {$released} scheduled announcement(s).");

        return self::SUCCESS;
    }
}
