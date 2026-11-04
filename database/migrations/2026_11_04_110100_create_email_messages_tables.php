<?php

use App\Enums\EmailFormat;
use App\Enums\EmailMessageStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `email_messages` is one row per submitted recipient and
     * `email_message_events` keeps every delivery, bounce or complaint receipt
     * the mail server posted for it, so the delivery report can be rebuilt from
     * the feedback rather than from our own guess.
     */
    public function up(): void
    {
        Schema::create('email_messages', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 64)->unique();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_contact_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('provider')->default('log');
            $table->string('provider_message_id', 191)->nullable();
            $table->string('to_address', 191);
            $table->string('from_address', 191);
            $table->string('from_name')->nullable();
            $table->string('subject');
            $table->longText('body');
            $table->string('format', 16)->default(EmailFormat::Html->value);
            $table->string('status')->default(EmailMessageStatus::Queued->value)->index();
            $table->string('provider_status', 64)->nullable();
            $table->unsignedTinyInteger('attempts')->default(1);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->decimal('cost', 14, 4)->default(0);
            $table->string('error_message')->nullable();
            $table->json('last_event')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'status']);
            $table->index(['client_id', 'submitted_at']);
            $table->unique(['provider', 'provider_message_id']);
        });

        Schema::create('email_message_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_message_id')->constrained()->cascadeOnDelete();
            $table->string('type')->index();
            $table->string('provider_status', 64)->nullable();
            $table->string('provider_event_id', 191)->nullable();
            $table->string('error_message')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['email_message_id', 'provider_event_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_message_events');
        Schema::dropIfExists('email_messages');
    }
};
