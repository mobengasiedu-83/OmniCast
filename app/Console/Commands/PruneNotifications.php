<?php

namespace App\Console\Commands;

use App\Enums\AnnouncementStatus;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Deletes inbox rows and announcements that are past their retention window.
 *
 * Only an inbox row the account has already read is pruned: an unread notice is
 * the account's to dismiss. Announcements that have been delivered for longer
 * than the window go with their receipts, so the admin history stays bounded.
 *
 * Orphans are swept on the same run, whatever their age: a receipt whose account
 * or announcement was deleted points at nothing, and a notice whose owner is gone
 * can never be read. `--dry-run` reports the same counts without deleting, which
 * is how a retention change is sized before it is trusted.
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
        {--sent-days= : How long to keep a sent announcement (defaults to the configured window)}
        {--dry-run : Report what would be deleted without deleting it}';

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
        $dryRun = (bool) $this->option('dry-run');

        $deletedNotifications = $this->prune(
            DB::table('notifications')
                ->whereNotNull('read_at')
                ->where('read_at', '<', now()->subDays($readDays)),
            $dryRun,
        );

        $deletedAnnouncements = $this->prune(
            Announcement::query()
                ->where('status', AnnouncementStatus::Sent->value)
                ->whereNotNull('sent_at')
                ->where('sent_at', '<', now()->subDays($sentDays))
                ->toBase(),
            $dryRun,
        );

        $orphanNotifications = $this->prune(
            DB::table('notifications')
                ->where('notifiable_type', (new User)->getMorphClass())
                ->whereNotExists(fn (Builder $query): Builder => $query
                    ->select(DB::raw(1))
                    ->from('users')
                    ->whereColumn('users.id', 'notifications.notifiable_id')),
            $dryRun,
        );

        $orphanReceipts = $this->prune(
            DB::table('announcement_recipients')
                ->whereNotExists(fn (Builder $query): Builder => $query
                    ->select(DB::raw(1))
                    ->from('users')
                    ->whereColumn('users.id', 'announcement_recipients.user_id'))
                ->orWhereNotExists(fn (Builder $query): Builder => $query
                    ->select(DB::raw(1))
                    ->from('announcements')
                    ->whereColumn('announcements.id', 'announcement_recipients.announcement_id')),
            $dryRun,
        );

        $verb = $dryRun ? 'Would prune' : 'Pruned';

        $this->info("{$verb} {$deletedNotifications} read notification(s) and {$deletedAnnouncements} sent announcement(s).");
        $this->info("{$verb} {$orphanNotifications} orphaned notification(s) and {$orphanReceipts} orphaned receipt(s).");

        return self::SUCCESS;
    }

    /**
     * Delete the rows the query selects, or count them when this is a dry run.
     */
    protected function prune(Builder $query, bool $dryRun): int
    {
        return $dryRun ? $query->count() : $query->delete();
    }
}
