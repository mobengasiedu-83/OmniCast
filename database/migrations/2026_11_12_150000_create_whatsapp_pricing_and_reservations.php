<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_pricing_rates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('market', 3);
            $table->string('category', 24);
            $table->unsignedInteger('volume_from')->default(0);
            $table->unsignedInteger('volume_to')->nullable();
            $table->string('currency', 3)->default('USD');
            $table->decimal('meta_rate', 14, 6);
            $table->decimal('provider_fee', 14, 6)->default(0);
            $table->decimal('direct_cost', 14, 6)->default(0);
            $table->decimal('fx_rate', 14, 6);
            $table->decimal('markup_percent', 8, 4)->default(0);
            $table->decimal('minimum_price', 14, 4)->default(0);
            $table->decimal('selling_price', 14, 4);
            $table->string('status', 20)->default('pending');
            $table->string('source', 255)->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['client_id', 'market', 'category', 'volume_from', 'status', 'effective_from'], 'wa_pricing_lookup_idx');
        });

        Schema::create('whatsapp_wallet_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->unique()->constrained('whatsapp_campaigns')->cascadeOnDelete();
            $table->foreignId('wallet_id')->constrained()->restrictOnDelete();
            $table->uuid('reference')->unique();
            $table->decimal('amount', 14, 4);
            $table->decimal('captured_amount', 14, 4)->default(0);
            $table->decimal('released_amount', 14, 4)->default(0);
            $table->string('status', 20)->default('held')->index();
            $table->timestamp('unknown_deadline_at')->nullable();
            $table->timestamp('exception_flagged_at')->nullable();
            $table->timestamps();
            $table->index(['wallet_id', 'status']);
        });
        Schema::table('whatsapp_campaigns', function (Blueprint $table): void {
            $table->foreign('wallet_reservation_id')->references('id')->on('whatsapp_wallet_reservations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_campaigns', function (Blueprint $table): void {
            $table->dropForeign(['wallet_reservation_id']);
        });
        Schema::dropIfExists('whatsapp_wallet_reservations');
        Schema::dropIfExists('whatsapp_pricing_rates');
    }
};
