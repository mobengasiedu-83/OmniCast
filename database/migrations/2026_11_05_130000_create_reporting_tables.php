<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Reporting
|--------------------------------------------------------------------------
|
| `report_daily_stats` is the rollup the analytics screens read their trends
| from: one row per client, day and channel, refreshed by `reports:aggregate`.
|
| `report_exports` is the async export queue. A row is written before the job
| runs so the requester can watch it move from queued to ready, and the unique
| key keeps a scheduled report from being generated twice for one period.
|
*/

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_daily_stats', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('channel', 24);
            $table->unsignedInteger('campaigns')->default(0);
            $table->unsignedInteger('recipients')->default(0);
            $table->unsignedInteger('delivered')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->unsignedInteger('skipped')->default(0);
            $table->decimal('spend', 14, 4)->default(0);
            $table->timestamps();

            $table->unique(['client_id', 'date', 'channel']);
            $table->index(['date', 'client_id']);
        });

        Schema::create('report_exports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('scope', 32);
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('report', 32);
            $table->string('format', 8)->default('csv');
            $table->string('status', 16)->default('pending');
            $table->json('filters')->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->string('source', 16)->default('manual');
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->unsignedInteger('rows')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('emailed_at')->nullable();
            $table->timestamps();

            $table->unique(['scope', 'report', 'period_start', 'period_end']);
            $table->index(['client_id', 'status']);
            $table->index(['requested_by', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_exports');
        Schema::dropIfExists('report_daily_stats');
    }
};
