<?php

use App\Enums\SmppBindMode;
use App\Enums\SmppMessageStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The SMPP server binds an ESME per account, records every session it
     * accepted, every `submit_sm` it ingested and every receipt it still owes
     * the ESME as a `deliver_sm`.
     */
    public function up(): void
    {
        Schema::create('smpp_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('system_id', 64)->unique();
            $table->string('password_hash');
            $table->string('bind_mode', 16)->default(SmppBindMode::Transceiver->value);
            $table->json('allowed_sources')->nullable();
            $table->unsignedSmallInteger('throughput_per_second')->default(10);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_bound_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['client_id', 'is_active']);
        });

        Schema::create('smpp_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('smpp_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('system_id', 64);
            $table->string('mode', 16);
            $table->string('remote_address', 64)->nullable();
            $table->timestamp('bound_at');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('unbound_at')->nullable();
            $table->unsignedInteger('pdus_in')->default(0);
            $table->unsignedInteger('pdus_out')->default(0);
            $table->timestamps();

            $table->index(['system_id', 'unbound_at']);
        });

        Schema::create('smpp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('smpp_account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('smpp_session_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference', 64)->unique();
            $table->string('message_id', 64)->unique();
            $table->string('source_address', 32);
            $table->string('destination_address', 32);
            $table->text('body');
            $table->string('encoding', 16)->default('gsm7');
            $table->unsignedSmallInteger('segments')->default(1);
            $table->string('status', 16)->default(SmppMessageStatus::Accepted->value)->index();
            $table->string('provider', 32)->nullable();
            $table->string('provider_message_id')->nullable();
            $table->string('error_message')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'created_at']);
        });

        Schema::create('smpp_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('smpp_message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('smpp_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('system_id', 64);
            $table->string('stat', 16);
            $table->string('err', 8)->nullable();
            $table->string('text')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['system_id', 'sent_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('smpp_deliveries');
        Schema::dropIfExists('smpp_messages');
        Schema::dropIfExists('smpp_sessions');
        Schema::dropIfExists('smpp_accounts');
    }
};
