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
        Schema::create('whatsapp_template_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('connector_id')->constrained('whatsapp_connectors')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('whatsapp_template_id')->nullable()->constrained('whatsapp_templates')->nullOnDelete();
            $table->string('name', 120);
            $table->string('language', 16)->default('en');
            $table->string('category', 32);
            $table->json('components');
            $table->char('content_hash', 64);
            $table->string('review_status', 24)->default('pending');
            $table->string('provider_status', 24)->default('not_submitted');
            $table->string('rejection_reason', 255)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['client_id', 'review_status', 'created_at'], 'wa_template_submissions_client_review_idx');
            $table->index(['connector_id', 'name', 'language'], 'wa_template_submissions_provider_match_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_template_submissions');
    }
};
