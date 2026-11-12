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
        Schema::table('whatsapp_connectors', function (Blueprint $table): void {
            $table->string('connector_name', 120)->nullable()->after('external_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_connectors', function (Blueprint $table): void {
            $table->dropColumn('connector_name');
        });
    }
};
