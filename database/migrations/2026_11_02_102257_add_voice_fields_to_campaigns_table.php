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
            $table->text('voice_script')->nullable()->after('sms_encoding');
            $table->string('voice_recording_path')->nullable()->after('voice_script');
            $table->unsignedSmallInteger('voice_max_duration_seconds')->default(60)->after('voice_recording_path');
            $table->foreignId('caller_id')->nullable()->after('sender_id')->constrained('caller_ids')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('caller_id');
            $table->dropColumn(['voice_script', 'voice_recording_path', 'voice_max_duration_seconds']);
        });
    }
};
