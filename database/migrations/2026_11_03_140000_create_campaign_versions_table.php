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
        Schema::create('campaign_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('status', 32);
            $table->string('fingerprint', 64);
            $table->json('snapshot');
            $table->unsignedInteger('audience_size')->default(0);
            $table->decimal('estimated_cost', 14, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('frozen_at')->nullable();
            $table->timestamps();

            $table->unique(['campaign_id', 'version']);
            $table->index(['campaign_id', 'status']);
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->string('idempotency_key', 64)->nullable()->after('reference');
            $table->unsignedInteger('current_version')->default(0)->after('status');
            $table->foreignId('duplicated_from_id')->nullable()->after('client_id')->constrained('campaigns')->nullOnDelete();
            $table->unique(['client_id', 'idempotency_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropUnique(['client_id', 'idempotency_key']);
            $table->dropConstrainedForeignId('duplicated_from_id');
            $table->dropColumn(['idempotency_key', 'current_version']);
        });

        Schema::dropIfExists('campaign_versions');
    }
};
