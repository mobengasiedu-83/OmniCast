<?php

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wallet_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reference')->unique();
            $table->string('provider')->default('paystack');
            $table->string('provider_reference')->nullable()->index();
            $table->string('status')->default(PaymentStatus::Pending->value);
            $table->string('currency', 3)->default('GHS');
            $table->decimal('amount', 14, 2);
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->string('package_key')->nullable()->index();
            $table->string('description');
            $table->string('channel')->nullable();
            $table->string('authorization_url', 512)->nullable();
            $table->string('access_code')->nullable();
            $table->string('failure_reason', 512)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'status']);
            $table->index(['client_id', 'created_at']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('number')->unique();
            $table->string('status')->default(InvoiceStatus::Paid->value)->index();
            $table->string('currency', 3)->default('GHS');
            $table->decimal('subtotal', 14, 2);
            $table->decimal('tax', 14, 2)->default(0);
            $table->decimal('total', 14, 2);
            $table->json('lines')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('issued_at');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'issued_at']);
        });

        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->foreignId('payment_id')->nullable()->after('billing_reservation_id')
                ->constrained('payments')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropForeign(['payment_id']);
            $table->dropColumn('payment_id');
        });

        Schema::dropIfExists('invoices');
        Schema::dropIfExists('payments');
    }
};
