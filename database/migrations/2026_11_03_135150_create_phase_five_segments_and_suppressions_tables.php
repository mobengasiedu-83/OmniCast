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
        Schema::create('contact_suppressions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('channel', 24);
            $table->string('identifier', 191);
            $table->string('reason', 32);
            $table->string('source', 64);
            $table->boolean('is_active')->default(true);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('suppressed_at')->useCurrent();
            $table->timestamp('lifted_at')->nullable();
            $table->timestamps();
            $table->index(['channel', 'identifier', 'is_active']);
            $table->index(['client_id', 'channel', 'is_active']);
        });

        Schema::create('contact_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('filters');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['client_id', 'name']);
        });

        Schema::table('campaign_contacts', function (Blueprint $table) {
            $table->json('recipient_snapshot')->nullable()->after('contact_id');
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->foreignId('contact_segment_id')->nullable()->after('contact_list_id')->constrained('contact_segments')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaign_contacts', function (Blueprint $table) {
            $table->dropColumn('recipient_snapshot');
        });
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contact_segment_id');
        });
        Schema::dropIfExists('contact_segments');
        Schema::dropIfExists('contact_suppressions');
    }
};
