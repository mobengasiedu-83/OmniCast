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
        Schema::table('campaigns', function (Blueprint $table) {
            $table->string('audio_status')->default('pending')->after('voice_max_duration_seconds');
            $table->timestamp('audio_reviewed_at')->nullable()->after('audio_status');
            $table->foreignId('audio_reviewed_by')->nullable()->after('audio_reviewed_at')->constrained('users')->nullOnDelete();
            $table->text('audio_rejection_reason')->nullable()->after('audio_reviewed_by');
            $table->index(['audio_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropIndex(['audio_status']);
            $table->dropConstrainedForeignId('audio_reviewed_by');
            $table->dropColumn(['audio_status', 'audio_reviewed_at', 'audio_rejection_reason']);
        });
    }
};
