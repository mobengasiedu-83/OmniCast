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
            $table->foreignId('client_id')->nullable()->after('provider_id')->constrained()->cascadeOnDelete();
            $table->string('business_id', 120)->nullable()->after('waba_id');
            $table->text('access_token')->nullable()->after('business_id');
            $table->timestamp('token_expires_at')->nullable()->after('access_token');
            $table->index(['client_id', 'status'], 'wa_connector_client_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_connectors', function (Blueprint $table): void {
            $table->dropIndex('wa_connector_client_status_idx');
            $table->dropConstrainedForeignId('client_id');
            $table->dropColumn(['business_id', 'access_token', 'token_expires_at']);
        });
    }
};
