<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('whatsapp_providers')
            && Schema::hasColumn('whatsapp_providers', 'base_url')
            && DB::table('whatsapp_providers')->whereNotNull('base_url')->where('base_url', '!=', '')->exists()
            && ! filled(config('whatsapp.provider.base_url'))) {
            throw new RuntimeException('A WhatsApp provider base URL exists in the database. Set WHATSAPP_API_BASE_URL to the provider origin before migrating; the database URL column will then be removed.');
        }

        if (Schema::hasTable('whatsapp_provider_credentials')
            && DB::table('whatsapp_provider_credentials')->exists()
            && (! filled(config('whatsapp.provider.client_id')) || ! filled(config('whatsapp.provider.api_key')))) {
            throw new RuntimeException('WhatsApp provider credentials exist in the database. Configure WHATSAPP_API_CLIENT_ID and WHATSAPP_API_KEY in the environment before migrating; credential rows will then be removed.');
        }

        if (Schema::hasTable('whatsapp_provider_credentials')) {
            Schema::drop('whatsapp_provider_credentials');
        }

        if (Schema::hasColumn('whatsapp_providers', 'base_url')) {
            Schema::table('whatsapp_providers', function (Blueprint $table): void {
                $table->dropColumn('base_url');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('whatsapp_providers') && ! Schema::hasColumn('whatsapp_providers', 'base_url')) {
            Schema::table('whatsapp_providers', function (Blueprint $table): void {
                $table->string('base_url')->nullable();
            });
        }
    }
};
