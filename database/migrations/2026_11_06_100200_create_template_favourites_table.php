<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Favourites are per operator rather than per tenant: two colleagues in the
     * same organisation keep their own shortlist of starting points.
     */
    public function up(): void
    {
        Schema::create('template_favourites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('message_template_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'message_template_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('template_favourites');
    }
};
