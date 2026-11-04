<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Replaces the per-channel consent model with a single DND (do not disturb) flag,
 * because every uploaded contact is treated as already having given consent.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex(['client_id', 'is_do_not_contact']);
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->renameColumn('is_do_not_contact', 'dnd');
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn(['consent_status', 'opted_out_at']);
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->timestamp('dnd_at')->nullable()->after('dnd');
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->string('first_name')->nullable()->change();
            $table->index(['client_id', 'dnd']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex(['client_id', 'dnd']);
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn('dnd_at');
            $table->string('consent_status', 24)->default('unknown');
            $table->timestamp('opted_out_at')->nullable();
            $table->string('first_name')->nullable(false)->change();
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->renameColumn('dnd', 'is_do_not_contact');
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->index(['client_id', 'is_do_not_contact']);
        });
    }
};
