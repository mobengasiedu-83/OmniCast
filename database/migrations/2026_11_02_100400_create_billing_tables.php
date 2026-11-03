<?php

use App\Enums\BillingUnit;
use App\Enums\ReservationStatus;
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
        Schema::create('rate_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('channel');
            $table->string('currency', 3)->default('GHS');
            $table->boolean('is_default')->default(false);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'channel', 'is_default']);
        });

        Schema::create('rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rate_card_id')->constrained()->cascadeOnDelete();
            $table->string('channel');
            $table->string('destination', 32)->default('GH');
            $table->string('destination_label')->nullable();
            $table->string('billing_unit')->default(BillingUnit::PerSmsPart->value);
            $table->decimal('unit_price', 12, 4)->default(0);
            $table->decimal('supplier_cost', 12, 4)->default(0);
            $table->timestamps();

            $table->unique(['rate_card_id', 'channel', 'destination']);
        });

        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('currency', 3)->default('GHS');
            $table->decimal('balance', 14, 2)->default(0);
            $table->decimal('reserved_balance', 14, 2)->default(0);
            $table->decimal('low_balance_threshold', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('billing_reservation_id')->nullable();
            $table->string('type');
            $table->decimal('amount', 14, 2);
            $table->decimal('balance_after', 14, 2);
            $table->string('description');
            $table->string('reference')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['wallet_id', 'created_at']);
        });

        Schema::create('billing_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->decimal('captured_amount', 14, 2)->default(0);
            $table->decimal('released_amount', 14, 2)->default(0);
            $table->string('status')->default(ReservationStatus::Held->value)->index();
            $table->timestamps();

            $table->index(['campaign_id', 'status']);
        });

        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->foreign('billing_reservation_id')
                ->references('id')
                ->on('billing_reservations')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropForeign(['billing_reservation_id']);
        });

        Schema::dropIfExists('billing_reservations');
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('wallets');
        Schema::dropIfExists('rates');
        Schema::dropIfExists('rate_cards');
    }
};
