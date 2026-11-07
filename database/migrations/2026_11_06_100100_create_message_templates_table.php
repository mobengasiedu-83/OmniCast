<?php

use App\Enums\Channel;
use App\Enums\TemplateStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A template holds the reusable copy for one channel. `client_id` is null
     * for a system template the platform publishes to everybody; otherwise the
     * row is a tenant template and stays inside that tenant. `origin_template_id`
     * remembers the system template a tenant customised so the library can show
     * what it was copied from without sharing the copy itself.
     */
    public function up(): void
    {
        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('template_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('origin_template_id')->nullable()
                ->constrained('message_templates')
                ->nullOnDelete();
            $table->string('name', 120);
            $table->string('channel', 16)->default(Channel::Sms->value);
            $table->string('language', 8)->default('en');
            $table->string('description')->nullable();
            $table->string('subject')->nullable();
            $table->text('body');
            $table->json('variables')->nullable();
            $table->string('status', 16)->default(TemplateStatus::Draft->value);
            $table->unsignedInteger('usage_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['client_id', 'channel', 'status']);
            $table->index(['template_category_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_templates');
    }
};
