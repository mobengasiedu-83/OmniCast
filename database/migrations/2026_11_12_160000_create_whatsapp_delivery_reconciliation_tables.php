<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_provider_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('provider_id')->constrained('whatsapp_providers')->restrictOnDelete();
            $table->foreignId('recipient_id')->nullable()->constrained('whatsapp_campaign_recipients')->nullOnDelete();
            $table->string('provider_event_id', 191);
            $table->string('provider_message_id', 191)->nullable();
            $table->string('event_type', 48);
            $table->string('status', 24)->default('unknown');
            $table->timestamp('occurred_at')->nullable();
            $table->boolean('signature_verified')->default(false);
            $table->boolean('billable')->default(false);
            $table->json('payload_redacted')->nullable();
            $table->string('processing_status', 24)->default('unmatched');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['provider_id', 'provider_event_id'], 'wa_provider_event_dedupe_idx');
            $table->index(['provider_id', 'provider_message_id']);
            $table->index(['processing_status', 'created_at']);
        });

        Schema::create('whatsapp_provider_invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('provider_id')->constrained('whatsapp_providers')->restrictOnDelete();
            $table->string('invoice_number', 191);
            $table->date('period_start');
            $table->date('period_end');
            $table->string('currency', 3);
            $table->decimal('total_amount', 14, 4);
            $table->string('status', 24)->default('imported');
            $table->json('source_metadata')->nullable();
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['provider_id', 'invoice_number'], 'wa_invoice_provider_number_idx');
        });

        Schema::create('whatsapp_usage_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('provider_id')->constrained('whatsapp_providers')->restrictOnDelete();
            $table->foreignId('recipient_id')->nullable()->constrained('whatsapp_campaign_recipients')->nullOnDelete();
            $table->foreignId('provider_event_id')->nullable()->unique()->constrained('whatsapp_provider_events')->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('whatsapp_provider_invoices')->nullOnDelete();
            $table->string('provider_usage_id', 191)->nullable();
            $table->string('provider_message_id', 191)->nullable();
            $table->string('category', 24)->nullable();
            $table->string('market', 3)->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('amount', 14, 4);
            $table->decimal('internal_cost', 14, 4)->nullable();
            $table->string('currency', 3);
            $table->timestamp('used_at')->nullable();
            $table->json('evidence')->nullable();
            $table->timestamps();
            $table->unique(['provider_id', 'provider_usage_id'], 'wa_usage_provider_ref_idx');
            $table->unique(['provider_id', 'provider_message_id'], 'wa_usage_provider_message_idx');
            $table->index(['provider_id', 'used_at']);
        });

        Schema::create('whatsapp_invoice_reconciliations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained('whatsapp_provider_invoices')->cascadeOnDelete();
            $table->foreignId('usage_record_id')->nullable()->constrained('whatsapp_usage_records')->nullOnDelete();
            $table->string('provider_reference', 191)->nullable();
            $table->decimal('provider_amount', 14, 4)->default(0);
            $table->decimal('internal_amount', 14, 4)->default(0);
            $table->decimal('variance', 14, 4)->default(0);
            $table->string('status', 24)->default('review_required');
            $table->text('review_note')->nullable();
            $table->json('details')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['invoice_id', 'provider_reference'], 'wa_recon_invoice_reference_idx');
            $table->index(['invoice_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_invoice_reconciliations');
        Schema::dropIfExists('whatsapp_usage_records');
        Schema::dropIfExists('whatsapp_provider_invoices');
        Schema::dropIfExists('whatsapp_provider_events');
    }
};
