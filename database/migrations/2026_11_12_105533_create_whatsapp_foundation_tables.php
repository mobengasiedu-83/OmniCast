<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_providers', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('driver', 40)->default('pro_plus');
            $table->string('base_url')->nullable();
            $table->string('status', 24)->default('unconfigured');
            $table->boolean('is_enabled')->default(false);
            $table->unsignedSmallInteger('connect_timeout')->default(5);
            $table->unsignedSmallInteger('request_timeout')->default(20);
            $table->timestamp('tested_at')->nullable();
            $table->string('last_test_error')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'driver']);
        });

        Schema::create('whatsapp_provider_credentials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('provider_id')->unique()->constrained('whatsapp_providers')->cascadeOnDelete();
            $table->text('client_id');
            $table->text('api_key');
            $table->timestamp('rotated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_connectors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('provider_id')->constrained('whatsapp_providers')->cascadeOnDelete();
            $table->string('external_id', 120);
            $table->string('waba_id', 120)->nullable();
            $table->string('sender_number', 32)->nullable();
            $table->string('status', 24)->default('available');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->unique(['provider_id', 'external_id']);
            $table->index(['provider_id', 'waba_id']);
            $table->index(['sender_number', 'status']);
        });

        Schema::create('whatsapp_client_connectors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('connector_id')->constrained('whatsapp_connectors')->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            $table->string('approval_status', 24)->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['client_id', 'connector_id']);
            $table->index(['client_id', 'enabled', 'approval_status'], 'wa_client_conn_tenant_enabled_approval_idx');
            $table->index(['connector_id', 'approval_status'], 'wa_client_conn_connector_approval_idx');
        });

        Schema::create('whatsapp_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 120);
            $table->string('subject_type', 120)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('properties')->nullable();
            $table->timestamp('occurred_at');
            $table->index(['client_id', 'occurred_at']);
            $table->index(['subject_type', 'subject_id']);
            $table->index(['action', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_audit_logs');
        Schema::dropIfExists('whatsapp_client_connectors');
        Schema::dropIfExists('whatsapp_connectors');
        Schema::dropIfExists('whatsapp_provider_credentials');
        Schema::dropIfExists('whatsapp_providers');
    }
};
