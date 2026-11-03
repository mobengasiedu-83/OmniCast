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
        Schema::table('clients', function (Blueprint $table) {
            $table->string('status_reason')->nullable()->after('status');
            $table->timestamp('status_changed_at')->nullable()->after('status_reason');
            $table->timestamp('activated_at')->nullable()->after('status_changed_at');
            $table->timestamp('suspended_at')->nullable()->after('activated_at');
            $table->timestamp('archived_at')->nullable()->after('suspended_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'status_reason',
                'status_changed_at',
                'activated_at',
                'suspended_at',
                'archived_at',
            ]);
        });
    }
};
