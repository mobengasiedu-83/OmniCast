<?php

use App\Enums\ClientRegistrationStatus;
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
        Schema::create('client_registrations', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('contact_name');
            $table->string('email')->index();
            $table->string('phone', 32);
            $table->string('country', 64)->nullable();
            $table->string('timezone')->default('Africa/Accra');
            $table->string('website')->nullable();
            $table->json('services');
            $table->unsignedInteger('expected_monthly_volume')->nullable();
            $table->text('message')->nullable();
            $table->string('status')->default(ClientRegistrationStatus::Pending->value)->index();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_registrations');
    }
};
