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
        /*
        |--------------------------------------------------------------------------
        | Routing: teams and categories
        |--------------------------------------------------------------------------
        |
        | A team is the mailbox an enquiry is handed to (support, sales, technical,
        | finance, compliance). A category is the reason a visitor or client is
        | writing, and it names the team that owns it so routing needs no code.
        |
        */

        Schema::create('support_teams', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('slug', 80)->unique();
            $table->string('email', 160)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('support_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('slug', 80)->unique();
            $table->text('description')->nullable();
            $table->string('audience', 16)->default('both');
            $table->foreignId('support_team_id')->nullable()->constrained()->nullOnDelete();
            $table->string('default_priority', 16)->default('normal');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['audience', 'is_active', 'sort_order']);
        });

        /*
        |--------------------------------------------------------------------------
        | The register
        |--------------------------------------------------------------------------
        |
        | One table holds both the public contact form (`type = enquiry`) and the
        | signed-in client's trackable ticket (`type = ticket`), because they are
        | the same conversation: a reference, a thread, an owner, a status. The
        | admin screens filter on `type`, so a Sales enquiry and a delivery
        | complaint are read in the two places a team expects to find them.
        |
        */

        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();
            $table->string('type', 16)->default('ticket');
            $table->string('source', 16)->default('public');
            $table->foreignId('support_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('support_team_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 160);
            $table->string('email', 160);
            $table->string('phone', 40)->nullable();
            $table->string('company', 160)->nullable();
            $table->string('subject', 200);
            $table->text('description');
            $table->string('priority', 16)->default('normal');
            $table->string('status', 24)->default('new');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('wallet_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('consent_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('first_responded_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('last_reply_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index(['status', 'priority']);
            $table->index(['client_id', 'status']);
            $table->index(['support_team_id', 'status']);
            $table->index('created_at');
        });

        /*
        |--------------------------------------------------------------------------
        | The conversation
        |--------------------------------------------------------------------------
        |
        | The opening description is copied into the first message so a thread can
        | be read in one place, and every later reply follows it. An internal note
        | is stored in the same table but is never shown to the requester.
        |
        */

        Schema::create('support_ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('author_name', 160);
            $table->string('author_email', 160)->nullable();
            $table->text('body');
            $table->boolean('is_internal')->default(false);
            $table->string('source', 16)->default('requester');
            $table->timestamps();

            $table->index(['support_ticket_id', 'created_at']);
        });

        Schema::create('support_ticket_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('support_ticket_message_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('storage_disk', 40)->default('local');
            $table->string('original_name', 255);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | Help centre
        |--------------------------------------------------------------------------
        |
        | The same articles answer a visitor on the public help centre and a client
        | in the support menu, so one published library serves both.
        |
        */

        Schema::create('support_articles', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->string('slug', 200)->unique();
            $table->string('category', 80)->nullable();
            $table->text('excerpt')->nullable();
            $table->longText('body');
            $table->boolean('is_published')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('views')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_published', 'sort_order']);
            $table->index('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_categories');
        Schema::dropIfExists('support_teams');
    }
};
