<?php

use App\Enums\CampaignContactStatus;
use App\Enums\CampaignStatus;
use App\Enums\Channel;
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
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('channel')->default(Channel::Sms->value);
            $table->string('status')->default(CampaignStatus::Draft->value)->index();
            $table->foreignId('contact_list_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sender_id')->nullable()->constrained('sender_ids')->nullOnDelete();
            $table->text('sms_message')->nullable();
            $table->string('sms_encoding')->nullable();
            $table->unsignedInteger('estimated_recipients')->default(0);
            $table->unsignedSmallInteger('estimated_sms_parts')->default(0);
            $table->decimal('estimated_cost', 14, 2)->default(0);
            $table->decimal('reserved_amount', 14, 2)->default(0);
            $table->decimal('actual_cost', 14, 2)->default(0);
            $table->string('schedule_type')->default('now');
            $table->timestamp('scheduled_at')->nullable();
            $table->time('window_starts_at')->nullable();
            $table->time('window_ends_at')->nullable();
            $table->string('timezone')->default('Africa/Accra');
            $table->unsignedTinyInteger('max_retries')->default(3);
            $table->unsignedSmallInteger('retry_delay_minutes')->default(30);
            $table->text('notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('launched_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['client_id', 'status']);
            $table->index(['client_id', 'channel']);
        });

        Schema::create('campaign_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('channel');
            $table->string('status')->default(CampaignStatus::Draft->value);
            $table->json('config')->nullable();
            $table->timestamps();

            $table->unique(['campaign_id', 'channel']);
        });

        Schema::create('campaign_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default(CampaignContactStatus::Pending->value)->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->decimal('cost', 14, 4)->default(0);
            $table->string('error_message')->nullable();
            $table->timestamps();

            $table->unique(['campaign_id', 'contact_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_contacts');
        Schema::dropIfExists('campaign_channels');
        Schema::dropIfExists('campaigns');
    }
};
