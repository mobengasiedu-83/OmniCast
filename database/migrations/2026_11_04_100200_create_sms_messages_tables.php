<?php

use App\Enums\SmsMessageStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `sms_messages` is one row per submitted recipient, `sms_message_events`
     * keeps every delivery receipt the network posted for it, so the delivery
     * report can be rebuilt from the receipts rather than from our own guess.
     */
    public function up(): void
    {
        Schema::create('sms_messages', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 64)->unique();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_contact_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('provider')->default('log');
            $table->string('provider_message_id')->nullable();
            $table->string('to_number', 32);
            $table->string('from_number', 32);
            $table->text('body');
            $table->string('encoding', 16)->default('gsm7');
            $table->unsignedSmallInteger('segments')->default(1);
            $table->string('status')->default(SmsMessageStatus::Queued->value)->index();
            $table->string('provider_status', 64)->nullable();
            $table->unsignedTinyInteger('attempts')->default(1);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->decimal('cost', 14, 4)->default(0);
            $table->string('error_message')->nullable();
            $table->json('last_event')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'status']);
            $table->index(['client_id', 'submitted_at']);
            $table->unique(['provider', 'provider_message_id']);
        });

        Schema::create('sms_message_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sms_message_id')->constrained()->cascadeOnDelete();
            $table->string('type')->index();
            $table->string('provider_status', 64)->nullable();
            $table->string('provider_event_id', 191)->nullable();
            $table->string('error_message')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['sms_message_id', 'provider_event_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sms_message_events');
        Schema::dropIfExists('sms_messages');
    }
};
