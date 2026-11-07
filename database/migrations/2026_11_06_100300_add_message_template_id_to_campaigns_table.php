<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The campaign remembers which library template it started from, so the
     * library can report usage and a reviewer can see where the copy came from.
     * The campaign's own content stays the source of truth: deleting a template
     * must never rewrite a campaign that has already been submitted.
     */
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->foreignId('message_template_id')->nullable()
                ->after('sender_id')
                ->constrained('message_templates')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('message_template_id');
        });
    }
};
