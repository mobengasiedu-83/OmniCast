<?php

use App\Enums\ContactConsentStatus;
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
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('phone', 32);
            $table->string('original_phone', 32)->nullable();
            $table->string('alternative_phone', 32)->nullable();
            $table->string('email')->nullable();
            $table->string('company')->nullable();
            $table->string('account_number')->nullable();
            $table->string('customer_id')->nullable();
            $table->string('location')->nullable();
            $table->string('language', 8)->default('en');
            $table->string('timezone')->default('Africa/Accra');
            $table->string('consent_status')->default(ContactConsentStatus::Unknown->value);
            $table->boolean('is_do_not_contact')->default(false);
            $table->timestamp('opted_out_at')->nullable();
            $table->json('custom_fields')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['client_id', 'phone']);
            $table->index(['client_id', 'is_do_not_contact']);
            $table->index(['client_id', 'last_name', 'first_name']);
        });

        Schema::create('contact_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['client_id', 'name']);
        });

        Schema::create('contact_list_contact', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->timestamp('added_at')->nullable();
            $table->timestamps();

            $table->unique(['contact_list_id', 'contact_id']);
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('color', 24)->default('slate');
            $table->timestamps();

            $table->unique(['client_id', 'name']);
        });

        Schema::create('contact_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['contact_id', 'tag_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_tag');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('contact_list_contact');
        Schema::dropIfExists('contact_lists');
        Schema::dropIfExists('contacts');
    }
};
