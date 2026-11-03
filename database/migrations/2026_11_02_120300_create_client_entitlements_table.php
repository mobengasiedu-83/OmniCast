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
        Schema::create('client_entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('service');
            $table->boolean('is_enabled')->default(true);
            $table->unsignedInteger('monthly_quota')->nullable();
            $table->unsignedInteger('daily_quota')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['client_id', 'service']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_entitlements');
    }
};
