<?php

namespace App\Console\Commands;

use App\Enums\AnnouncementStatus;
use App\Enums\PrivacyRetentionCategory;
use App\Models\Announcement;
use App\Models\User;
use App\Services\Privacy\PrivacyRetentionPolicyService;
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
 * Each category is disabled until a legal-approved retention period is recorded.
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
    public function handle(PrivacyRetentionPolicyService $policies): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $orphanedNotifications = DB::table('notifications')
            ->where('notifiable_type', (new User)->getMorphClass())
            ->whereNotExists(fn (Builder $users): Builder => $users
                ->selectRaw('1')
                ->from('users')
                ->whereColumn('users.id', 'notifications.notifiable_id'));
        $orphanedCount = $dryRun ? $orphanedNotifications->count() : $orphanedNotifications->delete();
        $readDays = $policies->approvedDays(PrivacyRetentionCategory::ReadNotifications);
        $sentDays = $policies->approvedDays(PrivacyRetentionCategory::SentAnnouncements);
        $deletedNotifications = 0;
        $deletedAnnouncements = 0;

        $orphanVerb = $dryRun ? 'Would prune' : 'Pruned';
        $this->info("{$orphanVerb} {$orphanedCount} orphaned notification(s).");

        if ($readDays === null) {
            $this->line('Skipped read notifications: no legally approved retention period is configured.');
        } else {
            $readDays = max(
                $readDays,
                (int) ($this->option('read-days') ?: config('notifications.retention.read_days', $readDays)),
            );
            $deletedNotifications = $this->prune(
                DB::table('notifications')
                    ->whereNotNull('read_at')
                    ->where('read_at', '<', now()->subDays($readDays)),
                $dryRun,
            );
        }

        if ($sentDays === null) {
            $this->line('Skipped sent announcements: no legally approved retention period is configured.');
        } else {
            $sentDays = max(
                $sentDays,
                (int) ($this->option('sent-days') ?: config('notifications.retention.sent_days', $sentDays)),
            );
            $sentAnnouncements = Announcement::query()
                ->where('status', AnnouncementStatus::Sent->value)
                ->whereNotNull('sent_at')
                ->where('sent_at', '<', now()->subDays($sentDays))
                ->toBase();

            $deletedAnnouncements = $dryRun
                ? $sentAnnouncements->count()
                : DB::transaction(function () use ($sentAnnouncements): int {
                    DB::table('announcement_recipients')
                        ->whereIn('announcement_id', (clone $sentAnnouncements)->select('id'))
                        ->delete();

                    return $sentAnnouncements->delete();
                });
        }

        $verb = $dryRun ? 'Would prune' : 'Pruned';
        $this->info("{$verb} {$deletedNotifications} read notification(s) and {$deletedAnnouncements} sent announcement(s).");

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
