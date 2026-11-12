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
        Schema::create('whatsapp_template_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('template_id')->constrained('whatsapp_templates')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->char('content_hash', 64);
            $table->string('name', 120);
            $table->string('language', 16);
            $table->string('category', 32)->nullable();
            $table->string('status', 24);
            $table->json('components')->nullable();
            $table->timestamp('captured_at');
            $table->timestamps();
            $table->unique(['template_id', 'version'], 'wa_template_versions_template_version_unique');
            $table->unique(['template_id', 'content_hash'], 'wa_template_versions_template_hash_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_template_versions');
    }
};
