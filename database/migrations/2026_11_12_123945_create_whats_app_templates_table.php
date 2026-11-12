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
        Schema::create('whatsapp_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('connector_id')->constrained('whatsapp_connectors')->cascadeOnDelete();
            $table->string('meta_template_id', 120);
            $table->string('name', 120);
            $table->string('language', 16)->default('en');
            $table->string('category', 32)->nullable();
            $table->string('status', 24)->default('unknown');
            $table->json('components')->nullable();
            $table->char('content_hash', 64);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->unique(['connector_id', 'meta_template_id'], 'wa_templates_connector_meta_unique');
            $table->index(['connector_id', 'status', 'name'], 'wa_templates_connector_status_name_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_templates');
    }
};
