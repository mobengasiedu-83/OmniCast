<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | The SMS channel
        |--------------------------------------------------------------------------
        |
        | Off for every account by default: an SMS costs money per part, so the
        | account opts in explicitly and the platform also has to enable the
        | channel in `config/notifications.php` before anything is dialled.
        |
        */

        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->boolean('sms')->default(false)->after('email');
        });

        /*
        |--------------------------------------------------------------------------
        | Scheduled announcements
        |--------------------------------------------------------------------------
        |
        | A composer can hold a message until a time it names. The schedule scans
        | `status` + `scheduled_at`, so the index mirrors that pair.
        |
        */

        Schema::table('announcements', function (Blueprint $table) {
            $table->timestamp('scheduled_at')->nullable()->after('sent_at');

            $table->index(['status', 'scheduled_at']);
        });

        Schema::table('announcement_recipients', function (Blueprint $table) {
            $table->timestamp('texted_at')->nullable()->after('emailed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('announcement_recipients', function (Blueprint $table) {
            $table->dropColumn('texted_at');
        });

        Schema::table('announcements', function (Blueprint $table) {
            $table->dropIndex(['status', 'scheduled_at']);
            $table->dropColumn('scheduled_at');
        });

        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->dropColumn('sms');
        });
    }
};
