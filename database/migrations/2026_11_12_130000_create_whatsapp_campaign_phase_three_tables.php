<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_contact_consents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->string('purpose', 40);
            $table->string('source', 120);
            $table->text('wording');
            $table->timestamp('granted_at');
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['client_id', 'contact_id', 'purpose', 'revoked_at'], 'wa_consent_eligibility_idx');
        });

        Schema::create('whatsapp_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('connector_id')->constrained('whatsapp_connectors')->restrictOnDelete();
            $table->foreignId('template_id')->constrained('whatsapp_templates')->restrictOnDelete();
            $table->string('name', 120);
            $table->string('status', 24)->default('draft');
            $table->unsignedBigInteger('contact_list_id')->nullable();
            $table->unsignedBigInteger('contact_segment_id')->nullable();
            $table->json('variable_mapping')->nullable();
            $table->foreignId('media_asset_id')->nullable()->constrained('whatsapp_media_assets')->nullOnDelete();
            $table->string('schedule_type', 20)->default('now');
            $table->timestamp('scheduled_at')->nullable();
            $table->string('timezone', 64)->default('Africa/Accra');
            $table->unsignedInteger('estimated_recipients')->default(0);
            $table->decimal('estimated_cost', 14, 4)->nullable();
            $table->string('estimate_currency', 3)->default('GHS');
            $table->json('estimate_disclaimer')->nullable();
            $table->json('price_snapshot')->nullable();
            $table->unsignedBigInteger('wallet_reservation_id')->nullable();
            $table->string('control_state', 24)->default('active');
            $table->string('dispatch_uuid', 36)->nullable()->unique();
            $table->timestamp('dispatch_started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['client_id', 'status', 'scheduled_at'], 'wa_campaign_tenant_status_schedule_idx');
        });

        Schema::create('whatsapp_campaign_recipients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained('whatsapp_campaigns')->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('consent_id')->nullable()->constrained('whatsapp_contact_consents')->nullOnDelete();
            $table->string('phone', 32);
            $table->json('variables_json')->nullable();
            $table->string('eligibility', 24)->default('eligible');
            $table->json('errors_json')->nullable();
            $table->json('snapshot_json');
            $table->timestamp('consent_checked_at')->nullable();
            $table->string('status', 24)->default('pending');
            $table->string('provider_message_id', 191)->nullable()->index();
            $table->unsignedTinyInteger('attempt_count')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('unknown_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('last_error_category', 32)->nullable();
            $table->text('last_error')->nullable();
            $table->decimal('unit_price', 14, 4)->nullable();
            $table->json('unit_price_snapshot')->nullable();
            $table->timestamps();
            $table->unique(['campaign_id', 'phone']);
            $table->index(['campaign_id', 'eligibility']);
        });

        Schema::create('whatsapp_message_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('recipient_id')->constrained('whatsapp_campaign_recipients')->cascadeOnDelete();
            $table->unsignedTinyInteger('attempt_number');
            $table->uuid('correlation_id')->unique();
            $table->string('status', 24);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('provider_reference', 191)->nullable();
            $table->string('error_category', 32)->nullable();
            $table->string('error_message')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->json('request_payload_redacted')->nullable();
            $table->json('response_payload_redacted')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->unique(['recipient_id', 'attempt_number']);
            $table->index(['status', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_message_attempts');
        Schema::dropIfExists('whatsapp_campaign_recipients');
        Schema::dropIfExists('whatsapp_campaigns');
        Schema::dropIfExists('whatsapp_contact_consents');
    }
};
