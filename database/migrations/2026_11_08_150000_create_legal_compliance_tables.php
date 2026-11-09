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
        | Policy documents
        |--------------------------------------------------------------------------
        |
        | One row per document the platform publishes (`terms`, `privacy`, ...).
        | The row holds the identity and the acceptance policy; the text lives in
        | versioned rows so a customer's acceptance can always be read back
        | against the exact wording they agreed to.
        |
        */

        Schema::create('legal_documents', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('title', 160);
            $table->text('summary')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('requires_acceptance_at_registration')->default(false);
            $table->boolean('requires_acceptance_on_campaign_submission')->default(false);
            $table->boolean('requires_acceptance_on_wallet_top_up')->default(false);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('legal_document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_document_id')->constrained()->cascadeOnDelete();
            $table->string('version', 20);
            $table->string('status', 16)->default('draft');
            $table->longText('body');
            $table->text('change_summary')->nullable();
            $table->date('effective_on')->nullable();
            $table->boolean('requires_reacceptance')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->unique(['legal_document_id', 'version']);
            $table->index(['legal_document_id', 'status']);
            $table->index(['status', 'effective_on']);
        });

        /*
        |--------------------------------------------------------------------------
        | Acceptance records
        |--------------------------------------------------------------------------
        |
        | A row is written the moment a customer accepts a document, naming the
        | version, the account, the organisation and the screen it happened on.
        | Nothing is ever updated in place: a later acceptance of a later version
        | is a new row, which is what makes the history auditable.
        |
        */

        Schema::create('legal_acceptances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legal_document_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('context', 32);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('accepted_at');
            $table->timestamps();

            $table->unique(['legal_document_version_id', 'user_id', 'context'], 'legal_acceptance_unique');
            $table->index(['legal_document_id', 'accepted_at']);
            $table->index(['client_id', 'context']);
        });

        /*
        |--------------------------------------------------------------------------
        | Policy notifications
        |--------------------------------------------------------------------------
        |
        | One row per account a published version was announced to, including the
        | inbox row it produced. The admin screens use it to answer "who has been
        | told about this version, and who has acknowledged it".
        |
        */

        Schema::create('legal_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legal_document_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('notification_id')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();

            $table->index(['legal_document_version_id', 'user_id']);
            $table->index(['client_id', 'acknowledged_at']);
        });

        /*
        |--------------------------------------------------------------------------
        | Organisation data processing agreements
        |--------------------------------------------------------------------------
        |
        | The DPA is agreed per organisation. A tenant with no row is treated as
        | "not started"; asking for one moves it to "requested" and the platform
        | team records the signature when the countersigned copy comes back.
        |
        */

        Schema::create('organisation_dpas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('legal_document_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('legal_document_version_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 24)->default('not-started');
            $table->string('signatory_name', 120)->nullable();
            $table->string('signatory_email', 160)->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organisation_dpas');
        Schema::dropIfExists('legal_notifications');
        Schema::dropIfExists('legal_acceptances');
        Schema::dropIfExists('legal_document_versions');
        Schema::dropIfExists('legal_documents');
    }
};
