<?php

use App\Enums\VoiceCallStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `voice_calls` is one row per dialled recipient, `voice_call_events` keeps
     * every callback the switch posted for it (ringing, answered, each DTMF
     * digit and the hang-up) so the call report can be rebuilt from the events.
     */
    public function up(): void
    {
        Schema::create('voice_calls', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 64)->unique();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_contact_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('provider')->default('log');
            $table->string('provider_call_id')->nullable();
            $table->string('to_number', 32);
            $table->string('from_number', 32);
            $table->string('status')->default(VoiceCallStatus::Queued->value)->index();
            $table->string('ivr_action')->nullable();
            $table->string('dtmf_digits', 32)->nullable();
            $table->string('hangup_cause')->nullable();
            $table->unsignedTinyInteger('attempts')->default(1);
            $table->timestamp('dialled_at')->nullable();
            $table->timestamp('ringing_at')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->decimal('cost', 14, 4)->default(0);
            $table->string('error_message')->nullable();
            $table->json('last_event')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'status']);
            $table->index(['client_id', 'dialled_at']);
            $table->unique(['provider', 'provider_call_id']);
        });

        Schema::create('voice_call_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voice_call_id')->constrained()->cascadeOnDelete();
            $table->string('type')->index();
            $table->string('digit', 8)->nullable();
            $table->string('ivr_action')->nullable();
            $table->string('provider_event_id', 191)->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->timestamp('occurred_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['voice_call_id', 'provider_event_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('voice_call_events');
        Schema::dropIfExists('voice_calls');
    }
};
