<?php

namespace App\Console\Commands;

use App\Enums\AnnouncementStatus;
use App\Models\Announcement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Deletes inbox rows and announcements that are past their retention window.
 *
 * Only an inbox row the account has already read is pruned: an unread notice is
 * the account's to dismiss. Announcements that have been delivered for longer
 * than the window go with their receipts, so the admin history stays bounded.
 */
class PruneNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:prune
        {--read-days= : How long to keep a read notification (defaults to the configured window)}
        {--sent-days= : How long to keep a sent announcement (defaults to the configured window)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete read notifications and sent announcements past their retention window';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $readDays = max((int) ($this->option('read-days') ?: config('notifications.retention.read_days', 90)), 1);
        $sentDays = max((int) ($this->option('sent-days') ?: config('notifications.retention.sent_days', 365)), 1);

        $deletedNotifications = DB::table('notifications')
            ->whereNotNull('read_at')
            ->where('read_at', '<', now()->subDays($readDays))
            ->delete();

        $deletedAnnouncements = Announcement::query()
            ->where('status', AnnouncementStatus::Sent->value)
            ->whereNotNull('sent_at')
            ->where('sent_at', '<', now()->subDays($sentDays))
            ->delete();

        $this->info("Pruned {$deletedNotifications} read notification(s) and {$deletedAnnouncements} sent announcement(s).");

        return self::SUCCESS;
    }
}
