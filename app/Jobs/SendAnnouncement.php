<?php

namespace App\Jobs;

use App\Enums\AnnouncementStatus;
use App\Models\Announcement;
use App\Services\Notifications\AnnouncementDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Fans one composed announcement out to its audience.
 *
 * The composer writes the row and queues this job: an announcement to every
 * tenant is thousands of notifications and must never run inside the request
 * that saved it. `WithoutOverlapping` keeps a retry from delivering twice, and
 * `deliverTo()` skips an account that already has a receipt.
 */
class SendAnnouncement implements ShouldQueue
{
    use Queueable;

    /**
     * How many times the worker may try to deliver the announcement.
     */
    public int $tries = 3;

    /**
     * How long a large fan-out may run.
     */
    public int $timeout = 300;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $announcementId)
    {
        $this->onQueue((string) config('notifications.queue', 'default'));
    }

    /**
     * The exponential backoff between attempts, in seconds.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    /**
     * Keep two workers from fanning the same announcement out at once.
     *
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new WithoutOverlapping('announcement-'.$this->announcementId)];
    }

    /**
     * Deliver the announcement, or finish the one an earlier attempt started.
     */
    public function handle(AnnouncementDispatcher $dispatcher): void
    {
        $announcement = Announcement::query()->find($this->announcementId);

        if ($announcement === null || $announcement->isSent()) {
            return;
        }

        $dispatcher->deliver($announcement);
    }

    /**
     * Put the announcement back to draft so the team can see it never went out.
     */
    public function failed(): void
    {
        Announcement::query()
            ->whereKey($this->announcementId)
            ->where('status', AnnouncementStatus::Queued->value)
            ->update(['status' => AnnouncementStatus::Draft->value]);
    }
}
