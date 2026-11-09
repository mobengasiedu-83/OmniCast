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
        | In-app notifications
        |--------------------------------------------------------------------------
        |
        | The framework's morph table: one row per user, written by the
        | notification dispatcher so the messaging centre can render the inbox
        | without asking the queue whether a job has run yet.
        |
        */

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['notifiable_type', 'notifiable_id', 'read_at']);
        });

        /*
        |--------------------------------------------------------------------------
        | Per-user channel preferences
        |--------------------------------------------------------------------------
        |
        | One row per user and category. A missing row means the defaults in
        | `config/notifications.php` apply, so a new category never silently
        | switches itself off for an existing account.
        |
        */

        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('category', 40);
            $table->boolean('in_app')->default(true);
            $table->boolean('email')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'category']);
        });

        /*
        |--------------------------------------------------------------------------
        | Platform announcements
        |--------------------------------------------------------------------------
        |
        | A message the platform team composes once and delivers to an audience
        | (everyone, the platform team, every tenant, one tenant or one role).
        | The composer writes the definition; the queued job fans it out.
        |
        */

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title', 120);
            $table->text('body');
            $table->string('category', 40)->default('platform');
            $table->string('audience', 24)->default('everyone');
            $table->foreignId('client_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('role', 40)->nullable();
            $table->string('severity', 16)->default('info');
            $table->boolean('send_email')->default(false);
            $table->string('status', 16)->default('draft');
            $table->timestamp('sent_at')->nullable();
            $table->unsignedInteger('recipient_count')->default(0);
            $table->unsignedInteger('read_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'sent_at']);
            $table->index(['audience', 'role']);
        });

        Schema::create('announcement_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('notification_id')->nullable();
            $table->timestamp('emailed_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique(['announcement_id', 'user_id']);
            $table->index(['user_id', 'read_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('announcement_recipients');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notifications');
    }
};
