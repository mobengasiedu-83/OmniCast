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
            $table->boolean('ivr_enabled')->default(false)->after('voice_max_duration_seconds');
            $table->text('ivr_prompt')->nullable()->after('ivr_enabled');
            $table->unsignedSmallInteger('ivr_timeout_seconds')->default(6)->after('ivr_prompt');
            $table->unsignedTinyInteger('ivr_max_attempts')->default(3)->after('ivr_timeout_seconds');
            $table->json('ivr_options')->nullable()->after('ivr_max_attempts');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn([
                'ivr_enabled',
                'ivr_prompt',
                'ivr_timeout_seconds',
                'ivr_max_attempts',
                'ivr_options',
            ]);
        });
    }
};
